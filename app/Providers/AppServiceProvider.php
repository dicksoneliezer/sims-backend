<?php

namespace App\Providers;

use App\Services\SchoolContext;
use App\Services\SchoolPresenceVerifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SchoolContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make('validator')->setPresenceVerifier(new SchoolPresenceVerifier($this->app['db']));
    }
}
