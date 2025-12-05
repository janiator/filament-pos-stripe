<?php

namespace App\Filament\Widgets;

use App\Actions\SyncEverythingFromStripe;
use App\Jobs\SyncEverythingFromStripeJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class SyncEverythingWidget extends Widget
{
    protected string $view = 'filament.widgets.sync-everything-widget';

    protected int | string | array $columnSpan = 'full';

    public function syncEverything(): void
    {
        // Dispatch the sync as a background job to avoid timeout issues
        SyncEverythingFromStripeJob::dispatch();

        Notification::make()
            ->title(__('filament.widgets.sync_everything.sync_started'))
            ->body(__('filament.widgets.sync_everything.sync_started_body'))
            ->success()
            ->send();
    }
}
