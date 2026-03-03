<?php

namespace Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource;

class EditWebflowSite extends EditRecord
{
    protected static string $resource = WebflowSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Do not overwrite api_token with empty when editing
        if (array_key_exists('api_token') && (empty($data['api_token']) || $data['api_token'] === '••••••••')) {
            unset($data['api_token']);
        }

        return $data;
    }
}
