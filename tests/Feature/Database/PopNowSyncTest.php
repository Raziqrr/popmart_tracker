<?php

use App\Models\PopNowBox;
use App\Models\PopNowSet;
use App\Services\PopMart\Sync\PopNowSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\Make;

function houseAccount()
{
    $account = Make::account(Make::user());
    config(['apis.popmart.house_account_id' => $account->id]);

    return $account;
}

it('polls a hot draw with the house account and stores the grid', function () {
    houseAccount();
    $product = Make::product(['business_type' => 'draw', 'sync_tier' => 'hot']);
    Http::fake(['*set/public_assignSet*' => Http::response(['json' => ['setNo' => 'S-9', 'boxes' => [
        ['position' => 1, 'boxNo' => '01', 'status' => 'available', 'isLocked' => false],
        ['position' => 2, 'boxNo' => '02', 'status' => 'available', 'isLocked' => true],
    ]]])]);

    $report = app(PopNowSync::class)->pollLive();

    $set = PopNowSet::where('product_id', $product->id)->first();
    $boxes = $set->boxes()->orderBy('box_no')->get();

    expect($report)->toMatchArray(['products' => 1, 'sets' => 1, 'stopped' => null])
        ->and($set->set_no)->toBe('S-9')
        ->and($set->total_boxes)->toBe(2)
        ->and($boxes->pluck('box_no')->all())->toBe(['01', '02'])
        ->and($boxes[0]->is_locked)->toBeFalse()
        // The house account never holds boxes, so any lock it sees belongs to someone else.
        ->and($boxes[1]->is_locked)->toBeTrue()
        ->and($boxes[1]->locked_by_other)->toBeTrue()
        ->and(DB::table('popmart_calls')->where('endpoint', 'AssignSet')->where('house', true)->where('lane', 'background')->count())->toBe(1);
});

it('leaves draws nobody is looking at alone', function () {
    houseAccount();
    Make::product(['business_type' => 'draw', 'sync_tier' => 'normal']);
    Http::fake();

    expect(app(PopNowSync::class)->pollLive())->toMatchArray(['products' => 0, 'sets' => 0]);
    Http::assertNothingSent();
});

it('polls a draw once someone has viewed it', function () {
    houseAccount();
    $product = Make::product(['business_type' => 'draw', 'sync_tier' => 'normal']);
    Http::fake(['*' => Http::response(['json' => ['setNo' => 'S-1', 'boxes' => []]])]);

    PopNowSync::markViewed($product->id);

    expect(app(PopNowSync::class)->pollLive())->toMatchArray(['products' => 1]);
});

it('does nothing without a house account', function () {
    config(['apis.popmart.house_account_id' => null]);
    Http::fake();

    expect(app(PopNowSync::class)->pollLive()['stopped'])->toContain('house account');
    Http::assertNothingSent();
});

it('lets a viewer refresh a set with their own session, read-only, at most once per cooldown', function () {
    $account = Make::account(Make::user());
    $product = Make::product(['business_type' => 'draw']);
    [$set, $boxes] = Make::set($product, 2);
    Http::fake(['*set/public_checkSetBoxLock*' => Http::response(['json' => ['isLocked' => true, 'isLockedByMe' => false, 'lockRemainingSeconds' => 120]])]);

    $sync = app(PopNowSync::class);

    expect($sync->refreshForViewer($set, $account))->toBe(['refreshed' => 2, 'cooldown' => false]);

    $box = $boxes[0]->fresh();
    expect($box->is_locked)->toBeTrue()
        ->and($box->lock_duration_seconds)->toBe(120);

    // Never the call that would release the viewer's own holds.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'assignSet'));

    expect($sync->refreshForViewer($set, $account))->toBe(['refreshed' => 0, 'cooldown' => true]);
    Http::assertSentCount(2);
});

it('skips sold boxes when refreshing', function () {
    $account = Make::account(Make::user());
    [$set, $boxes] = Make::set(Make::product(['business_type' => 'draw']), 3);
    PopNowBox::whereKey($boxes[0]->id)->update(['status' => 'sold']);
    Http::fake(['*' => Http::response(['json' => ['isLocked' => false, 'lockRemainingSeconds' => 0]])]);

    expect(app(PopNowSync::class)->refreshForViewer($set, $account)['refreshed'])->toBe(2);
});
