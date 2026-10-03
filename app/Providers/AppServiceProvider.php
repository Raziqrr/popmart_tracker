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
        // (resources/js/Pages/Auth). Guarded so the app still boots before
        // `composer require laravel/fortify`.
        if (class_exists(Fortify::class)) {
            Fortify::loginView(fn () => Inertia::render('Auth/Login'));
            Fortify::registerView(fn () => Inertia::render('Auth/Register'));
            // After logging in or registering, land on the dashboard (Fortify's default is /home).
            config(['fortify.home' => '/']);
        }
    }
}
