<?php

namespace App\Http\Presenters;

use App\Models\ProductEvent;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/** ProductEvent / ProductChange (resources/js/types/catalog.ts) from product_events. */
class EventFeed
{
    public function __construct(private ProductCards $cards) {}

    /**
     * The newest events with their product cards, for the activity feed.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 50): array
    {
        $events = ProductEvent::with('product')
            ->whereNotIn('type', ['tag_added', 'tag_removed'])
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();

        $products = $events->pluck('product')->filter()->unique('id')->values();
        $cards = collect($this->cards->present(new EloquentCollection($products->all())))->keyBy('id');

        return $events
            ->filter(fn (ProductEvent $e) => $cards->has($e->product_id))
            ->map(fn (ProductEvent $e) => ['id' => (string) $e->id, 'product' => $cards->get($e->product_id), ...self::change($e)])
            ->values()
            ->all();
    }

    /** @return array{type: string, occurred_at: string|null, from: mixed, to: mixed} */
    public static function change(ProductEvent $event): array
    {
        return [
            'type' => $event->type->value,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'from' => $event->from,
            'to' => $event->to,
        ];
    }
}
