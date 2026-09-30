<?php

use App\Models\UserCheckout;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
