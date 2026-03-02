<?php

namespace App\Filament\Resources\Collections\Schemas;

use App\Models\Collection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Select::make('parent_id')
                            ->label('Parent collection')
                            ->placeholder('None (root collection)')
                            ->options(function (): array {
                                $store = Filament::getTenant();
                                if (! $store) {
                                    return [];
                                }
                                $query = Collection::where('stripe_account_id', $store->stripe_account_id)
                                    ->orderBy('sort_order')
                                    ->orderBy('name');
                                $record = isset($this->record) ? $this->record : null;
                                if ($record) {
                                    $excludeIds = array_merge([$record->id], Collection::descendantIds($record->id));
                                    $query->whereNotIn('id', $excludeIds);
                                }
                                return $query->pluck('name', 'id')->all();
                            })
                            ->searchable()
                            ->columnSpanFull()
                            ->helperText('Optional parent for nested collections'),

                        TextInput::make('name')
                            ->label('Collection Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                // Auto-generate handle from name when user finishes typing
                                if ($state) {
                                    $set('handle', Str::slug($state));
                                }
                            })
                            ->columnSpanFull()
                            ->helperText('The name of the collection'),

                        TextInput::make('handle')
                            ->label('Handle (Slug)')
                            ->maxLength(255)
                            ->helperText('URL-friendly identifier (e.g., "summer-collection"). Auto-updates as you type the name.')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Optional description for this collection'),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active collections are visible'),
                    ]),

                Section::make('Media')
                    ->schema([
                        // Show current image if it exists
                        View::make('filament.resources.collections.components.image-preview')
                            ->key('image-preview')
                            ->visible(fn ($get, $record) => ($record && $record->image_url) || $get('image_url'))
                            ->columnSpanFull(),

                        FileUpload::make('image')
                            ->label('Collection Image')
                            ->image()
                            ->optimize('webp')
                            ->maxImageWidth(1920)
                            ->maxImageHeight(1920)
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->disk('public')
                            ->directory('collections')
                            ->visibility('public')
                            ->maxSize(5120) // 5MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->helperText('Upload an image for this collection (max 5MB). JPEG, PNG, WebP, and GIF are supported.')
                            ->default(function ($record) {
                                // Convert existing image_url back to file path if it's from our storage
                                if ($record && $record->image_url) {
                                    $url = $record->image_url;
                                    // Check if it's a storage URL
                                    $storageUrl = Storage::disk('public')->url('');
                                    if (str_starts_with($url, $storageUrl)) {
                                        // Extract the relative path
                                        $relativePath = str_replace($storageUrl, '', $url);
                                        return ltrim($relativePath, '/');
                                    }
                                }
                                return null;
                            })
                            // Note: image_url will be set in mutation methods after file is saved
                            ->columnSpanFull(),

                        TextInput::make('image_url')
                            ->label('Image URL')
                            ->url()
                            ->maxLength(255)
                            ->live()
                            ->helperText('Image URL (automatically set when uploading a file, or enter manually for external URLs)')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Section::make('Metadata')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Custom Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText('Additional metadata for this collection')
                            ->columnSpanFull()
                            ->addable(true)
                            ->deletable(true)
                            ->reorderable(false),
                    ])
                    ->collapsible()
                    ->collapsed(true),
            ]);
    }
}

