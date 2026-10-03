<?php

use App\Services\Alerts\AlertMessage;
use App\Services\Alerts\AlertPreferences;
use App\Services\Alerts\AlertTargets;
use App\Services\Alerts\TelegramChannel;
use App\Services\PopMart\Sync\ProductEventType;

/*
| Alert rules without a database: who hears about an event, and how
| preferences are read. The dispatcher's queries aren't covered here.
*/

function watch(int $userId, string $kind, string $id): array
{
    return ['user_id' => $userId, 'kind' => $kind, 'id' => $id];
}

function productEvent(ProductEventType $type, ?string $skuId = null): array
{
    return ['type' => $type, 'product_id' => 'p1', 'sku_id' => $skuId, 'collection_id' => 'c1'];
}

describe('who gets alerted', function () {
    $watches = [
        watch(1, 'product', 'p1'),     // watches the product
        watch(2, 'sku', 's1'),         // watches one figure of it
        watch(3, 'sku', 's9'),         // watches a figure of another product
        watch(4, 'collection', 'c1'),  // follows its collection
        watch(1, 'sku', 's1'),         // user 1 also watches the figure: still one alert
    ];
    $skuProduct = ['s1' => 'p1', 's2' => 'p1', 's9' => 'p9'];

    it('alerts product watchers about everything', function () use ($watches, $skuProduct) {
        expect(AlertTargets::users(productEvent(ProductEventType::LowStock, 's2'), $watches, $skuProduct))->toBe([1]);
    });

    it('alerts figure watchers about their figure and product-wide news', function () use ($watches, $skuProduct) {
        expect(AlertTargets::users(productEvent(ProductEventType::Restocked, 's1'), $watches, $skuProduct))->toBe([1, 2])
            ->and(AlertTargets::users(productEvent(ProductEventType::PriceDrop), $watches, $skuProduct))->toBe([1, 2]);
    });

    it('alerts collection followers only about listings, openings and pre-orders', function () use ($watches, $skuProduct) {
        expect(AlertTargets::users(productEvent(ProductEventType::Listed), $watches, $skuProduct))->toBe([1, 2, 4])
            ->and(AlertTargets::users(productEvent(ProductEventType::PreorderOpened), $watches, $skuProduct))->toBe([1, 2, 4])
            ->and(AlertTargets::users(productEvent(ProductEventType::SoldOut), $watches, $skuProduct))->toBe([1, 2]);
    });
});

describe('alert preferences', function () {
    it('defaults to the inbox and the main events', function () {
        $preferences = AlertPreferences::fromArray([]);

        expect($preferences->channels())->toBe(['database'])
            ->and($preferences->wants(ProductEventType::Restocked))->toBeTrue()
            ->and($preferences->wants(ProductEventType::SoldOut))->toBeFalse();
    });

    it('reads the user\'s choices and drops anything unknown or not alertable', function () {
        $preferences = AlertPreferences::fromArray([
            'types' => ['sold_out', 'tag_added', 'nonsense'],
            'email' => true,
            'telegram' => true,
            'telegram_chat_id' => '12345',
        ]);

        expect($preferences->types)->toBe([ProductEventType::SoldOut])
            ->and($preferences->channels())->toBe(['database', 'mail', TelegramChannel::class]);
    });

    it('needs a chat id before Telegram counts as on', function () {
        expect(AlertPreferences::fromArray(['telegram' => true])->channels())->toBe(['database']);
    });

    it('round-trips through toArray', function () {
        $preferences = AlertPreferences::fromArray(['types' => ['restocked'], 'email' => true]);

        expect(AlertPreferences::fromArray($preferences->toArray())->toArray())->toBe($preferences->toArray());
    });
});

it('has a title for every event type', function () {
    foreach (ProductEventType::cases() as $type) {
        expect(AlertMessage::title($type))->not->toBe('');
    }
});
