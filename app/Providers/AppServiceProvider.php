<?php

namespace App\Providers;

use App\Contracts\BoxPredictorContract;
use App\Services\Analytics\ExclusionBoxPredictor;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

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
        // Fortify handles the login and register posts; these are its pages
        // (resources/js/Pages/Auth).
        Fortify::loginView(fn () => Inertia::render('Auth/Login'));
        Fortify::registerView(fn () => Inertia::render('Auth/Register'));
    }
}
