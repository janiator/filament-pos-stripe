<?php

namespace App\Filament\Widgets;

use App\Actions\SyncEverythingFromStripe;
use Filament\Actions\Action;
use Filament\Widgets\Widget;

class SyncEverythingWidget extends Widget
{
    protected string $view = 'filament.widgets.sync-everything-widget';

    protected int | string | array $columnSpan = 'full';

    public function syncEverything(): void
    {
        $syncAction = new SyncEverythingFromStripe();
        $syncAction(true);
    }
}
