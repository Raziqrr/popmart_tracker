<?php

use App\Models\Collection;
use App\Models\Product;
use App\Models\UserCheckout;
use App\Services\PopMart\HouseAccount;
use App\Services\PopMart\PopMartThrottle;
use App\Services\Locator\Country;
use App\Services\PopMart\Sync\CatalogSweep;
use App\Services\PopMart\Sync\CollectionDiscovery;
use App\Services\PopMart\Sync\PopNowSync;
use App\Services\PopMart\Sync\StockSync;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Checkout doesn't extend a POP NOW hold, so an unpaid checkout is lost once its hold ends.
Artisan::command('popnow:expire-checkouts', function () {
    $count = 0;
    UserCheckout::overdue()->each(function (UserCheckout $checkout) use (&$count) {
        $checkout->markFailed(UserCheckout::FAILED_EXPIRED);
        $count++;
    });
    $this->info("Marked {$count} unpaid checkout(s) as failed.");
})->purpose('Fail POP NOW checkouts whose hold ran out before payment');

Schedule::command('popnow:expire-checkouts')->everyMinute()->withoutOverlapping();

/*
| Pop Mart sync (README.md, "Data sync"). Every call goes through
| PopMartThrottle, so a run that hits the budget just stops and the next run
| carries on.
*/

Artisan::command('popmart:sweep', function (CatalogSweep $sweep) {
    $report = $sweep->run();
    $this->info("Swept {$report['collections']} collection(s), found {$report['new_collections']} new: {$report['products']} product(s), {$report['events']} event(s), {$report['skipped']} skipped.");
    if ($report['stopped']) {
        $this->warn("Stopped early: {$report['stopped']}");
    }
})->purpose('Find new products, collections and changes: collection listings, live CMS content, missing details');

Artisan::command('popmart:add-collection {id : Pop Mart collectionId (from the collection page URL)} {--area=MY} {--name=}', function (CollectionDiscovery $discovery, CatalogSweep $sweep) {
    $area = Country::tryFrom(strtoupper($this->option('area')));
    if ($area === null) {
        $this->error('Unknown area '.$this->option('area').'. Known: '.implode(', ', array_column(Country::cases(), 'value')));

        return 1;
    }

    $collection = $discovery->ensure($this->argument('id'), $area, 'manual', $this->option('name'));
    $report = $sweep->sweepOne($collection);

    $this->info(($collection->wasRecentlyCreated ? 'Added' : 'Already had')." {$collection->name}: swept {$report['products']} product(s), {$report['events']} event(s).");
    if ($report['products'] === 0) {
        $this->warn('Pop Mart returned no products for it. Check the id, or run popmart:capture FindSummariesByCollectionId with it.');
    }
    if ($report['stopped']) {
        $this->warn("Stopped early: {$report['stopped']}");
    }
})->purpose('Add a Pop Mart collection by id and sweep it now');

Artisan::command('popmart:sync-stock {--batch=}', function (StockSync $stock) {
    $report = $stock->runDue($this->option('batch') ? (int) $this->option('batch') : null);
    $this->info("Checked {$report['checked']} product(s): {$report['snapshots']} stock change(s), {$report['events']} event(s), {$report['skipped']} skipped.");
    if ($report['stopped']) {
        $this->warn("Stopped early: {$report['stopped']}");
    }
})->purpose('Check stock for products that are due, by tier');

Artisan::command('popmart:poll-popnow', function (PopNowSync $popNow) {
    $report = $popNow->pollLive();
    $this->info("Polled {$report['sets']} set(s) across {$report['products']} live draw(s).");
    if ($report['stopped']) {
        $this->warn("Stopped: {$report['stopped']}");
    }
})->purpose('Poll live POP NOW box grids with the house account');

Artisan::command('popmart:prune-calls {--days=7}', function () {
    $deleted = DB::table('popmart_calls')->where('called_at', '<', now()->subDays((int) $this->option('days')))->delete();
    $this->info("Deleted {$deleted} old call log row(s).");
})->purpose('Trim the Pop Mart call log');

Artisan::command('popmart:status', function (PopMartThrottle $throttle, HouseAccount $house) {
    $lastHour = DB::table('popmart_calls')->where('called_at', '>=', now()->subHour());
    $this->table(['Last hour', 'Count'], [
        ['Calls', (clone $lastHour)->count()],
        ['429 from Pop Mart', (clone $lastHour)->where('status', 429)->count()],
        ['Failed (any error)', (clone $lastHour)->whereNotNull('error')->count()],
        ['Busiest minute', (clone $lastHour)->selectRaw("date_format(called_at, '%H:%i') as minute, count(*) as n")->groupBy('minute')->orderByDesc('n')->value('n') ?? 0],
    ]);

    $this->line('Paused for: '.$throttle->pausedFor().'s');
    $account = $house->get();
    $this->line('House account: '.($account ? "#{$account->id}, session ".($account->sessionIsValid() ? 'live' : 'EXPIRED') : 'not set'));
    $this->line('Products due for a stock check: '.Product::where(fn ($q) => $q->whereNull('next_stock_check_at')->orWhere('next_stock_check_at', '<=', now()))->count());
    $this->line('Collections: '.Collection::count().', '.Collection::where('name_confirmed', false)->count().' still with a placeholder name, '.Collection::where('created_at', '>=', now()->subDay())->count().' found in the last 24h');
    $this->line('By tier: '.Product::groupBy('sync_tier')->selectRaw('sync_tier, count(*) as n')->pluck('n', 'sync_tier')->map(fn ($n, $tier) => "{$tier} {$n}")->implode(', '));

    $fields = DB::table('popmart_payload_fields')->where('first_seen_at', '>=', now()->subDay())->orderBy('endpoint')->get(['endpoint', 'path', 'example']);
    $this->line("New response fields in the last 24h: {$fields->count()}");
    foreach ($fields as $field) {
        $this->line("  {$field->endpoint}: {$field->path} = {$field->example}");
    }
})->purpose('Pop Mart sync health: call volume, 429s, pause, house account, new fields');

Schedule::command('popmart:sweep')->everyThreeMinutes()->withoutOverlapping();
Schedule::command('popmart:sync-stock')->everyMinute()->withoutOverlapping();
Schedule::command('popmart:poll-popnow')->everyMinute()->withoutOverlapping();
Schedule::command('popmart:prune-calls')->daily();
