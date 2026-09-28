<?php

namespace App\Providers;

use App\Contracts\BoxPredictorContract;
use App\Services\Analytics\ExclusionBoxPredictor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BoxPredictorContract::class, ExclusionBoxPredictor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
