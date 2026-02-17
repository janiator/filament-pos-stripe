<?php

namespace Positiv\FilamentWebflow\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Positiv\FilamentWebflow\Jobs\PullWebflowItems;
use Positiv\FilamentWebflow\Jobs\PushWebflowItem;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Support\WebflowFieldData;

class WebflowCollectionItemsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-panels::pages.page';

    #[Url]
    /** @var int|string|null Collection ID from query string (string when from URL). */
    public int|string|null $collection = null;

    public ?WebflowCollection $collectionModel = null;

    public function mount(): void
    {
        if ($this->collection === null || $this->collection === '') {
            $this->redirectToCollectionSelector();

            return;
        }

        $tenant = Filament::getTenant();
        $collectionId = (int) $this->collection;
        $this->collectionModel = WebflowCollection::where('id', $collectionId)
            ->where('is_active', true)
            ->whereHas('site', fn ($q) => $tenant ? $q->where('store_id', $tenant->getKey()) : $q)
            ->firstOrFail();
    }

    protected function redirectToCollectionSelector(): void
    {
        // Redirect to first available collection or Webflow Sites
        $tenant = Filament::getTenant();
        $first = WebflowCollection::where('is_active', true)
            ->whereHas('site', fn ($q) => $tenant ? $q->where('store_id', $tenant->getKey()) : $q)
            ->first();
        if ($first) {
            $this->collection = $first->id;
            $this->collectionModel = $first;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->collectionModel
            ? 'CMS Items: '.$this->collectionModel->name
            : 'Webflow CMS Items';
    }

    public function table(Table $table): Table
    {
        $collection = $this->collectionModel;
        if (! $collection) {
            return $table
                ->query(WebflowItem::query()->whereRaw('1 = 0'))
                ->columns([]);
        }

        $columns = [
            TextColumn::make('id')->label('ID')->sortable()->toggleable(),
            IconColumn::make('is_published')->label('Published')->boolean()->toggleable(),
            IconColumn::make('is_draft')->label('Draft')->boolean()->toggleable(),
        ];

        $schema = static::orderSchemaFields($collection->schema ?? []);
        foreach ($schema as $field) {
            $slug = $field['slug'] ?? null;
            if (! $slug) {
                continue;
            }
            $label = $field['displayName'] ?? $slug;
            $type = $field['type'] ?? 'PlainText';

            if ($type === 'Image' || $type === 'MultiImage') {
                $columns[] = ImageColumn::make('field_data.'.$slug)
                    ->label($label)
                    ->getStateUsing(fn ($record) => WebflowFieldData::displayValue($record->field_data[$slug] ?? null))
                    ->circular(false)
                    ->toggleable();
            } else {
                $columns[] = TextColumn::make('field_data.'.$slug)
                    ->label($label)
                    ->formatStateUsing(fn ($state) => WebflowFieldData::displayValue($state))
                    ->limit(40)
                    ->searchable(query: function ($query, $search) use ($slug) {
                        $query->where('field_data->'.$slug, 'like', '%'.$search.'%');
                    })
                    ->toggleable();
            }
        }

        $columns[] = TextColumn::make('last_synced_at')->label('Last synced')->dateTime()->sortable()->toggleable();

        $editPageUrl = fn (WebflowItem $record): string => WebflowItemEditPage::getUrl(
            ['item' => $record->id],
            true,
            null,
            Filament::getTenant()
        );

        return $table
            ->query(WebflowItem::query()->where('webflow_collection_id', $collection->id))
            ->columns($columns)
            ->recordUrl($editPageUrl)
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Published')
                    ->placeholder('All')
                    ->trueLabel('Published')
                    ->falseLabel('Not published'),
                TernaryFilter::make('is_draft')
                    ->label('Draft')
                    ->placeholder('All')
                    ->trueLabel('Draft')
                    ->falseLabel('Not draft'),
            ])
            ->headerActions([
                Action::make('back')
                    ->label('Change collection')
                    ->url(static::getUrl())
                    ->openUrlInNewTab(false),
                Action::make('pullFromWebflow')
                    ->label('Pull from Webflow')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (): void {
                        PullWebflowItems::dispatch($this->collectionModel);
                        \Filament\Notifications\Notification::make()
                            ->title('Pull queued')
                            ->body('Items will be synced from Webflow shortly.')
                            ->success()
                            ->send();
                        $user = auth()->user();
                        if ($user) {
                            \Filament\Notifications\Notification::make()
                                ->title('Pull queued')
                                ->body('Items will be synced from Webflow shortly.')
                                ->success()
                                ->sendToDatabase($user);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Pull items from Webflow?'),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url($editPageUrl),
                \Filament\Actions\Action::make('pushToWebflow')
                    ->label('Push to Webflow')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->action(fn (WebflowItem $record) => PushWebflowItem::dispatch($record, false)),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('pushSelected')
                    ->label('Push selected to Webflow')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->action(fn ($records) => $records->each(fn (WebflowItem $r) => PushWebflowItem::dispatch($r, false)))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /**
     * Order schema fields so name and slug come before description, then the rest.
     *
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, array<string, mixed>>
     */
    protected static function orderSchemaFields(array $schema): array
    {
        $order = ['name' => 0, 'slug' => 1, 'description' => 2];
        usort($schema, function (array $a, array $b) use ($order): int {
            $slugA = $a['slug'] ?? '';
            $slugB = $b['slug'] ?? '';
            $posA = $order[$slugA] ?? 999;
            $posB = $order[$slugB] ?? 999;
            if ($posA !== $posB) {
                return $posA <=> $posB;
            }

            return 0;
        });

        return $schema;
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        // Parent already adds extra parameters (e.g. collection) as query string; do not append again
        return parent::getUrl($parameters, $isAbsolute, $panel, $tenant);
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'webflow-cms-items';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Webflow CMS';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }
}
