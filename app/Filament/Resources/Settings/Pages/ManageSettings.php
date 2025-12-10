<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingsResource;
use App\Models\Setting;
use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Schema;

class ManageSettings extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string $resource = SettingsResource::class;

    protected string $view = 'filament.resources.settings.pages.manage-settings';

    public ?Setting $record = null;

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        if (!$tenant) {
            abort(403, 'No tenant selected');
        }

        // Get or create settings for the current store
        $this->record = Setting::getForStore($tenant->id);
        
        // Fill form with record data
        $this->form->fill($this->record->toArray());
    }

    public function getTitle(): string
    {
        return 'Settings';
    }

    public function form(Schema $schema): Schema
    {
        // Configure the schema with the resource's form
        return SettingsResource::form($schema)
            ->model($this->record)
            ->statePath('data');
    }

    protected function getForms(): array
    {
        return [
            'form',
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant) {
            $data['store_id'] = $tenant->id;
        }

        $this->record->update($data);

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }

    public function getCachedFormActions(): array
    {
        return $this->getFormActions();
    }

    public function hasFullWidthFormActions(): bool
    {
        return false;
    }
}
