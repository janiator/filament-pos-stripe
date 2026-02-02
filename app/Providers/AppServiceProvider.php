<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\Query;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Nightwatch::rejectQueries(function (Query $query) {
            return str_contains($query->sql, 'into "jobs"');
        });

        Nightwatch::rejectQueries(function (Query $query) {
            return str_contains($query->sql, 'from "cache"')
                || str_contains($query->sql, 'into "cache"');
        });

        // Set Filament's default timezone to Oslo
        FilamentTimezone::set('Europe/Oslo');

        // Force HTTPS in local development when using Herd
        if (app()->environment('local') && str_starts_with(config('app.url', ''), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Configure Pulse authorization - allow all authenticated users
        Gate::define('viewPulse', function ($user) {
            return $user !== null;
        });

        // Register custom Media model for Spatie Media Library
        \Spatie\MediaLibrary\MediaCollections\Models\Media::resolveRelationUsing('model', function ($mediaModel) {
            return $mediaModel->morphTo();
        });

        // Listen to media collection cleared events to trigger Stripe sync
        \Illuminate\Support\Facades\Event::listen(
            \Spatie\MediaLibrary\MediaCollections\Events\CollectionHasBeenClearedEvent::class,
            \App\Listeners\SyncProductOnMediaDeleted::class
        );
    }
}
