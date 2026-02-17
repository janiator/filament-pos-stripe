<?php

namespace Positiv\FilamentWebflow;

use Illuminate\Support\ServiceProvider;

class WebflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-webflow.php',
            'filament-webflow'
        );
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-webflow');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-webflow.php' => config_path('filament-webflow.php'),
            ], 'filament-webflow-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'filament-webflow-migrations');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
