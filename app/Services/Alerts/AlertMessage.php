<?php

namespace App\Services\Alerts;

use App\Models\Product;
use App\Models\ProductEvent;
use App\Services\PopMart\Sync\ProductEventType;
use Illuminate\Support\Carbon;

/**
 * The words for one product event in an alert: a title and a detail line.
 * Mirrors changeMeta / changeDetail in resources/js/components/product/changeMeta.ts.
 */
final class AlertMessage
{
    public static function title(ProductEventType $type): string
    {
        return match ($type) {
            ProductEventType::Restocked => 'Restocked',
            ProductEventType::LowStock => 'Stock falling',
            ProductEventType::SoldOut => 'Sold out',
            ProductEventType::PriceDrop => 'Price drop',
            ProductEventType::PriceRise => 'Price up',
            ProductEventType::Listed => 'New listing',
            ProductEventType::SaleStarted => 'Sale opened',
            ProductEventType::SaleTimeChanged => 'Sale time set',
            ProductEventType::PreorderOpened => 'Pre-order opened',
            ProductEventType::PreorderClosed => 'Pre-order closed',
            ProductEventType::TagAdded => 'Tag added',
            ProductEventType::TagRemoved => 'Tag removed',
        };
    }

    public static function detail(ProductEvent $event, Product $product): string
    {
        $money = fn ($minor) => sprintf('%s %.2f', $product->currency, ((int) $minor) / 100);

        return match ($event->type) {
            ProductEventType::Restocked => $event->to !== null ? "Back in stock: {$event->to} available" : 'Back in stock',
            ProductEventType::LowStock => "Only {$event->to} left",
            ProductEventType::SoldOut => 'No stock left',
            ProductEventType::PriceDrop, ProductEventType::PriceRise => "{$money($event->from)} → {$money($event->to)}",
            ProductEventType::Listed => "Listed at {$money($product->price)}",
            ProductEventType::SaleStarted => "On sale now at {$money($product->price)}",
            ProductEventType::SaleTimeChanged => is_string($event->to) ? 'Opens '.Carbon::parse($event->to)->timezone(config('app.timezone'))->format('j M, H:i') : 'Sale time changed',
            ProductEventType::PreorderOpened => 'Buy now, ships later (early pass needed)',
            ProductEventType::PreorderClosed => 'Pre-order window ended',
            ProductEventType::TagAdded => "Tagged “{$event->to}”",
            ProductEventType::TagRemoved => "No longer “{$event->from}”",
        };
    }

    /** Where an alert links to. */
    public static function url(Product $product): string
    {
        return rtrim((string) config('app.url'), '/')."/products/{$product->slug}";
    }
}
