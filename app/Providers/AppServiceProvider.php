<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
