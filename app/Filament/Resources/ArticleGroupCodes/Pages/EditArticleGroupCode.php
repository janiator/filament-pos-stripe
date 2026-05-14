<?php

namespace App\Filament\Resources\ArticleGroupCodes\Pages;

use App\Filament\Resources\ArticleGroupCodes\ArticleGroupCodeResource;
use App\Filament\Resources\Pages\EditRecord;
use Filament\Actions\DeleteAction;

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
