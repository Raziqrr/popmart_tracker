<?php

namespace App\Http\Presenters;

use App\Models\AutoLockAttempt;
use App\Models\PopNowBox;
use App\Models\PopNowSet;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PopNowSets
{
    public function __construct(private ProductCards $productCards) {}

    /**
     * @param  Collection<int, PopNowSet>|array<PopNowSet>  $sets
     * @return list<array{set: array<string, mixed>, boxes: list<array<string, mixed>>}>
     */
    public function present(Collection|array $sets, ?User $user = null): array
    {
        if (! ($sets instanceof EloquentCollection)) {
            $sets = new EloquentCollection($sets);
        }

        if ($sets->isEmpty()) {
            return [];
        }

        $sets->loadMissing([
            'product.theme',
            'product.skus',
            'boxes.hints.sku',
            'boxes.reveals.sku',
        ]);

        $myLockedBoxIds = collect();
        if ($user) {
            $myLockedBoxIds = AutoLockAttempt::where('user_id', $user->id)
                ->where('status', 'locked')
                ->where(function ($q) {
                    $q->whereNull('lock_expires_at')->orWhere('lock_expires_at', '>', now());
                })
                ->pluck('pop_now_box_id')
                ->filter();
        }

        $allProducts = $sets->pluck('product')->filter()->unique('id');
        $presentedCards = collect($this->productCards->present(new EloquentCollection($allProducts->values()->all())))->keyBy('id');

        return $sets->map(function (PopNowSet $set) use ($presentedCards, $myLockedBoxIds) {
            $productCard = $presentedCards->get($set->product_id);

            $presentedBoxes = $set->boxes->sortBy('position')->values()->map(function (PopNowBox $box) use ($myLockedBoxIds) {
                $isMine = $myLockedBoxIds->contains($box->id);

                $state = 'available';
                if ($box->status === 'sold') {
                    $state = 'sold';
                } elseif ($box->is_locked) {
                    $state = $isMine ? 'locked_mine' : 'locked_other';
                }

                $hints = $box->hints->map(fn ($h) => [
                    'sku' => [
                        'id' => (string) $h->sku_id,
                        'name' => (string) ($h->sku?->name ?? ''),
                        'sku_code' => $h->sku?->sku_code,
                        'bar_code' => $h->sku?->bar_code,
                        'is_secret' => $h->sku?->box_type === 'secret',
                        'image_url' => $h->sku?->main_image,
                    ],
                    'source' => $h->source,
                    'confirmations' => (int) $h->confirmations,
                    'last_seen_at' => $h->last_seen_at?->toIso8601String() ?? $h->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ])->values()->all();

                $revealModel = $box->reveals->sortByDesc('revealed_at')->first();
                $reveal = $revealModel ? [
                    'sku' => [
                        'id' => (string) $revealModel->sku_id,
                        'name' => (string) ($revealModel->sku?->name ?? ''),
                        'sku_code' => $revealModel->sku?->sku_code,
                        'bar_code' => $revealModel->sku?->bar_code,
                        'is_secret' => $revealModel->sku?->box_type === 'secret',
                        'image_url' => $revealModel->sku?->main_image,
                    ],
                    'source' => $revealModel->source,
                    'revealed_at' => $revealModel->revealed_at?->toIso8601String() ?? now()->toIso8601String(),
                ] : null;

                return [
                    'id' => (int) $box->id,
                    'box_no' => (string) $box->box_no,
                    'position' => (int) $box->position,
                    'state' => $state,
                    'lock_expires_at' => $box->lockExpiresAt()?->toIso8601String(),
                    'hints' => $hints,
                    'reveal' => $reveal,
                    'last_seen_at' => $box->last_seen_at?->toIso8601String() ?? $box->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            })->values()->all();

            return [
                'set' => [
                    'id' => (int) $set->id,
                    'product' => $productCard,
                    'set_no' => (string) $set->set_no,
                    'width' => (int) ($set->set_width ?: 5),
                    'height' => (int) ($set->set_height ?: 1),
                    'total_boxes' => (int) ($set->total_boxes ?: count($presentedBoxes)),
                    'first_seen_at' => $set->first_seen_at?->toIso8601String() ?? $set->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'last_seen_at' => $set->last_seen_at?->toIso8601String() ?? $set->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ],
                'boxes' => $presentedBoxes,
            ];
        })->values()->all();
    }
}
