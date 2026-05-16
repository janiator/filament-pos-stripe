<?php

namespace App\Providers;

use App\Filament\Pages\WebflowItemEditPage;
use App\Listeners\SyncProductOnMediaDeleted;
use App\Observers\WebflowSiteObserver;
use App\Policies\WebflowSitePolicy;
use App\Queue\FailedJobs\DeduplicatingDatabaseUuidFailedJobProvider;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\Query;
use Positiv\FilamentWebflow\Models\WebflowSite;
use Spatie\MediaLibrary\MediaCollections\Events\CollectionHasBeenClearedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (config('queue.failed.driver') !== 'database-uuids') {
            return;
        }

        $this->app->singleton('queue.failer', function (Application $app) {
            return new DeduplicatingDatabaseUuidFailedJobProvider(
                $app['db'],
                (string) config('queue.failed.database'),
                (string) config('queue.failed.table'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(WebflowSite::class, WebflowSitePolicy::class);

        config([
            'filament-webflow.item_edit_page' => WebflowItemEditPage::class,
        ]);

        Nightwatch::rejectQueries(function (Query $query) {
            return str_contains($query->sql, 'into "jobs"');
        });

        Nightwatch::rejectQueries(function (Query $query) {
            return str_contains($query->sql, '"cache"');
        });

        // Set Filament's default timezone to Oslo
        FilamentTimezone::set('Europe/Oslo');

        // Force HTTPS in local development when using Herd
        if (app()->environment('local') && str_starts_with(config('app.url', ''), 'https://')) {
            URL::forceScheme('https');
        }

        // Configure Pulse authorization - allow all authenticated users
        Gate::define('viewPulse', function ($user) {
            return $user !== null;
        });

        // Register custom Media model for Spatie Media Library
        Media::resolveRelationUsing('model', function ($mediaModel) {
            return $mediaModel->morphTo();
        });

        // Listen to media collection cleared events to trigger Stripe sync
        Event::listen(
            CollectionHasBeenClearedEvent::class,
            SyncProductOnMediaDeleted::class
        );

        // When a Webflow site is deleted, remove EventTickets that referenced its items
        WebflowSite::observe(WebflowSiteObserver::class);
    }
}
