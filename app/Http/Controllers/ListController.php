<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\PinnedItem;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pins (dashboard) and watches (alerts) for products and collections
 * (capability:watchlist). Each POST toggles and returns the user's lists,
 * the same shape as the shared Inertia prop `lists`.
 */
class ListController extends Controller
{
    /** POST /api/pins { type, id } */
    public function togglePin(Request $request): JsonResponse
    {
        [$type, $id] = $this->target($request);
        $this->toggle(PinnedItem::class, 'pinnable', $request->user(), $type, $id);

        return response()->json(self::listsFor($request->user()));
    }

    /** POST /api/watches { type, id } */
    public function toggleWatch(Request $request): JsonResponse
    {
        [$type, $id] = $this->target($request);
        $this->toggle(WishlistItem::class, 'watchable', $request->user(), $type, $id);

        return response()->json(self::listsFor($request->user()));
    }

    /**
     * The user's pinned and watched ids, split by kind. Empty lists for guests.
     *
     * @return array{pinned_product_ids: list<string>, pinned_collection_ids: list<string>, watched_product_ids: list<string>, watched_collection_ids: list<string>}
     */
    public static function listsFor(?User $user): array
    {
        $empty = ['pinned_product_ids' => [], 'pinned_collection_ids' => [], 'watched_product_ids' => [], 'watched_collection_ids' => []];
        if ($user === null) {
            return $empty;
        }

        $product = (new Product)->getMorphClass();
        $collection = (new Collection)->getMorphClass();
        $pins = PinnedItem::where('user_id', $user->id)->get(['pinnable_type', 'pinnable_id'])->groupBy('pinnable_type');
        $watches = WishlistItem::where('user_id', $user->id)->get(['watchable_type', 'watchable_id'])->groupBy('watchable_type');

        return [
            'pinned_product_ids' => $pins->get($product)?->pluck('pinnable_id')->values()->all() ?? [],
            'pinned_collection_ids' => $pins->get($collection)?->pluck('pinnable_id')->values()->all() ?? [],
            'watched_product_ids' => $watches->get($product)?->pluck('watchable_id')->values()->all() ?? [],
            'watched_collection_ids' => $watches->get($collection)?->pluck('watchable_id')->values()->all() ?? [],
        ];
    }

    /** @return array{0: string, 1: string} morph class and id, checked to exist */
    private function target(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['product', 'collection'])],
            'id' => ['required', 'string'],
        ]);

        $model = $validated['type'] === 'product' ? Product::class : Collection::class;
        abort_unless($model::whereKey($validated['id'])->exists(), 404);

        return [(new $model)->getMorphClass(), $validated['id']];
    }

    /** @param class-string<PinnedItem|WishlistItem> $model */
    private function toggle(string $model, string $morph, User $user, string $type, string $id): void
    {
        $existing = $model::where('user_id', $user->id)->where("{$morph}_type", $type)->where("{$morph}_id", $id)->first();

        $existing ? $existing->delete() : $model::create(['user_id' => $user->id, "{$morph}_type" => $type, "{$morph}_id" => $id]);
    }
}
