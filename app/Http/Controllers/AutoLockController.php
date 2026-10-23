<?php

namespace App\Http\Controllers;

use App\Http\Presenters\ProductCards;
use App\Models\AutoLockAttempt;
use App\Models\AutoLockRule;
use App\Models\Product;
use App\Models\Sku;
use App\Services\AutoLock\AutoLockRunner;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Auto-lock rules and their attempts (capability:auto_lock, so a live Pop
 * Mart session is required). Shapes match AutoLockRule / LockAttempt in
 * resources/js/types/lock.ts. Users only ever see and change their own rules.
 */
class AutoLockController extends Controller
{
    /** Pop Mart's hold length, confirmed live (docs/tickets/auto-lock.md). */
    private const MAX_LOCK_SECONDS = 300;

    public function __construct(private ProductCards $cards) {}

    /** GET /api/auto-lock */
    public function index(Request $request): JsonResponse
    {
        $rules = AutoLockRule::where('user_id', $request->user()->id)->with(['product', 'picks.sku'])->latest()->get();
        $attempts = AutoLockAttempt::where('user_id', $request->user()->id)->with(['box.set', 'sku'])->latest('attempted_at')->limit(50)->get();

        return response()->json([
            'enabled_on_server' => AutoLockRunner::enabled(),
            'rules' => $this->presentRules($rules),
            'attempts' => $this->presentAttempts($attempts),
        ]);
    }

    /** POST /api/auto-lock */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'string', Rule::exists('products', 'id')->where('business_type', 'draw')],
            'target.kind' => ['required', Rule::in(['random', 'specific', 'ranked'])],
            'target.count' => ['exclude_if:target.kind,specific', 'required', 'integer', 'min:1'],
            'target.picks' => ['exclude_if:target.kind,random', 'required', 'array', 'min:1'],
            'target.picks.*.sku_id' => ['required', 'string', 'distinct'],
            'target.picks.*.count' => ['required_if:target.kind,specific', 'nullable', 'integer', 'min:1'],
            'target.picks.*.priority' => ['required_if:target.kind,ranked', 'nullable', 'integer', 'min:1'],
            'target.min_confirmations' => ['nullable', 'integer', 'min:1'],
            'target.verified_only' => ['nullable', 'boolean'],
            'trigger' => ['required', Rule::in(AutoLockRule::TRIGGERS)],
            'min_chance' => ['required_if:trigger,chance_reached', 'nullable', 'numeric', 'min:0.01', 'max:1'],
            'lock_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LOCK_SECONDS],
            'renew' => ['prohibited'],
            'checkout_immediately' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'acknowledged' => ['accepted'],
        ], [
            'product_id.exists' => 'Auto-lock only works on POP NOW draws.',
            'renew.prohibited' => "Auto-renew isn't supported: Pop Mart doesn't reset a held box's timer.",
            'acknowledged.accepted' => 'Tick the risk acknowledgement first.',
        ]);

        $target = $validated['target'];
        $picks = $target['picks'] ?? [];
        $this->checkPicks($validated['product_id'], $validated['trigger'], $target['kind'], $picks);

        $rule = DB::transaction(function () use ($request, $validated, $target, $picks) {
            $rule = AutoLockRule::create([
                'user_id' => $request->user()->id,
                'popmart_account_id' => $request->user()->livePopmartAccount()->id,
                'product_id' => $validated['product_id'],
                'target_kind' => $target['kind'],
                'count' => $target['kind'] === 'specific' ? null : $target['count'],
                'min_confirmations' => $target['min_confirmations'] ?? 1,
                'verified_only' => $target['verified_only'] ?? false,
                'trigger' => $validated['trigger'],
                'min_chance' => $validated['trigger'] === 'chance_reached' ? $validated['min_chance'] : null,
                'lock_duration_seconds' => $validated['lock_duration_seconds'] ?? self::MAX_LOCK_SECONDS,
                'checkout_immediately' => $validated['checkout_immediately'] ?? true,
                'enabled' => $validated['enabled'] ?? true,
                'expires_at' => $validated['expires_at'] ?? null,
                'acknowledged_at' => now(),
            ]);

            foreach ($picks as $pick) {
                $rule->picks()->create([
                    'sku_id' => $pick['sku_id'],
                    'count' => $target['kind'] === 'specific' ? $pick['count'] : null,
                    'priority' => $target['kind'] === 'ranked' ? $pick['priority'] : null,
                ]);
            }

            return $rule;
        });

        return response()->json($this->presentRules(new EloquentCollection([$rule->load(['product', 'picks.sku'])]))[0], 201);
    }

    /** PATCH /api/auto-lock/{rule}: switch on/off or change when it ends. */
    public function update(AutoLockRule $rule, Request $request): JsonResponse
    {
        abort_unless((int) $rule->user_id === (int) $request->user()->id, 404);

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        $rule->update($validated);

        return response()->json($this->presentRules(new EloquentCollection([$rule->load(['product', 'picks.sku'])]))[0]);
    }

    /** DELETE /api/auto-lock/{rule} */
    public function destroy(AutoLockRule $rule, Request $request): JsonResponse
    {
        abort_unless((int) $rule->user_id === (int) $request->user()->id, 404);
        $rule->delete();

        return response()->json(['deleted' => true]);
    }

    /** Picks must be figures of this product; chance triggers need picks and can't target a secret (no odds). */
    private function checkPicks(string $productId, string $trigger, string $kind, array $picks): void
    {
        if (in_array($trigger, AutoLockRule::CHANCE_TRIGGERS, true) && $kind === 'random') {
            throw ValidationException::withMessages(['trigger' => 'This trigger needs figures to aim for: use Specific or Ranked.']);
        }

        $skus = Sku::whereIn('id', array_column($picks, 'sku_id'))->get()->keyBy('id');

        foreach ($picks as $i => $pick) {
            $sku = $skus->get($pick['sku_id']);
            if ($sku === null || $sku->product_id !== $productId) {
                throw ValidationException::withMessages(["target.picks.{$i}.sku_id" => 'That figure is not part of this draw.']);
            }
            if ($sku->box_type === 'secret' && in_array($trigger, AutoLockRule::CHANCE_TRIGGERS, true)) {
                throw ValidationException::withMessages(["target.picks.{$i}.sku_id" => 'Secrets have no odds, so a chance trigger can never fire for one.']);
            }
        }
    }

    /** @return list<array<string, mixed>> AutoLockRule */
    public function presentRules(EloquentCollection $rules): array
    {
        $cards = collect($this->cards->present(new EloquentCollection($rules->pluck('product')->filter()->unique('id')->values()->all())))->keyBy('id');

        return $rules->map(function (AutoLockRule $rule) use ($cards) {
            $figure = fn ($pick) => [
                'sku_id' => $pick->sku_id,
                'name' => $pick->sku?->name,
                'image_url' => $pick->sku?->main_image,
                'is_secret' => $pick->sku?->box_type === 'secret',
            ];
            $trust = ['min_confirmations' => $rule->min_confirmations, 'verified_only' => $rule->verified_only];

            return [
                'id' => (string) $rule->id,
                'product' => $cards->get($rule->product_id),
                'target' => match ($rule->target_kind) {
                    'random' => ['kind' => 'random', 'count' => $rule->count],
                    'specific' => ['kind' => 'specific', 'picks' => $rule->picks->map(fn ($p) => ['figure' => $figure($p), 'count' => $p->count])->values()->all(), ...$trust],
                    'ranked' => ['kind' => 'ranked', 'count' => $rule->count, 'picks' => $rule->picks->sortBy('priority')->map(fn ($p) => ['figure' => $figure($p), 'priority' => $p->priority])->values()->all(), ...$trust],
                },
                'trigger' => $rule->trigger,
                'min_chance' => $rule->min_chance,
                'lock_duration_seconds' => $rule->lock_duration_seconds,
                'renew' => null,
                'checkout_immediately' => $rule->checkout_immediately,
                'locks_made' => $rule->locks_made,
                'enabled' => $rule->enabled,
                'expires_at' => $rule->expires_at?->toIso8601String(),
                'created_at' => $rule->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> LockAttempt */
    public function presentAttempts(EloquentCollection $attempts): array
    {
        $products = Product::whereIn('id', $attempts->map(fn ($a) => $a->box?->set?->product_id)->filter()->unique())->get();
        $cards = collect($this->cards->present($products))->keyBy('id');

        return $attempts->map(fn (AutoLockAttempt $a) => [
            'id' => (string) $a->id,
            'rule_id' => $a->auto_lock_rule_id ? (string) $a->auto_lock_rule_id : null,
            'product' => $cards->get($a->box?->set?->product_id),
            'set_no' => $a->box?->set?->set_no,
            'box_no' => $a->box?->box_no,
            'figure' => $a->sku ? ['sku_id' => $a->sku->id, 'name' => $a->sku->name, 'image_url' => $a->sku->main_image, 'is_secret' => $a->sku->box_type === 'secret'] : null,
            'status' => $a->status,
            'attempted_at' => $a->attempted_at?->toIso8601String(),
            'locked_at' => $a->locked_at?->toIso8601String(),
            'lock_expires_at' => $a->lock_expires_at?->toIso8601String(),
            'renewals' => 0,
            'hold_ends_at' => null,
            'error' => $a->error,
        ])->values()->all();
    }
}
