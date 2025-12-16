<?php

namespace App\Filament\Resources\ArticleGroupCodes\Pages;

use App\Filament\Resources\ArticleGroupCodes\ArticleGroupCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditArticleGroupCode extends EditRecord
{
    protected static string $resource = ArticleGroupCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn ($record) => $record && $record->is_standard),
        ];
    }
}
