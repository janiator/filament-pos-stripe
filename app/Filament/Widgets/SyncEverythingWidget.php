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
            ->title('Sync started')
            ->body('The sync is running in the background. You will be notified when it completes.')
            ->success()
            ->send();
    }
}
