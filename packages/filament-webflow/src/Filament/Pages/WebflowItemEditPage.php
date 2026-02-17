<?php

namespace Positiv\FilamentWebflow\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Support\Htmlable;
use Positiv\FilamentWebflow\Jobs\PushWebflowItem;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Support\WebflowSchemaFormBuilder;

class WebflowItemEditPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-panels::pages.page';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?string $previousUrl = null;

    protected ?WebflowItem $record = null;

    public function mount(int|string $item): void
    {
        $tenant = Filament::getTenant();
        $this->record = WebflowItem::query()
            ->with('collection.site')
            ->where('id', $item)
            ->whereHas('collection', function ($q) use ($tenant) {
                $q->where('is_active', true)
                    ->whereHas('site', fn ($q2) => $tenant ? $q2->where('store_id', $tenant->getKey()) : $q2);
            })
            ->firstOrFail();

        $this->data = $this->record->field_data ?? [];
        $this->stripImageFieldsFromState();
        $this->previousUrl = url()->previous();
    }

    public function getRecord(): ?WebflowItem
    {
        return $this->record;
    }

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();
        $collection = $record?->collection;

        return $collection
            ? 'Edit: '.$collection->name.' #'.$record->id
            : 'Edit CMS Item';
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'webflow-cms-items/{item}/edit';
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        return parent::getUrl($parameters, $isAbsolute, $panel, $tenant);
    }

    public function form(Schema $schema): Schema
    {
        $record = $this->getRecord();
        if (! $record) {
            return $schema->components([]);
        }

        $collection = $record->collection;
        if (! $collection instanceof WebflowCollection) {
            return $schema->components([]);
        }

        $components = WebflowSchemaFormBuilder::build($collection);
        if (empty($components)) {
            return $schema->statePath('data')->components([]);
        }

        return $schema
            ->statePath('data')
            ->model($this->getRecord())
            ->components($components);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make($this->getFormActions())
                        ->alignment(Alignment::Start),
                ]),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        $record = $this->getRecord();
        $listUrl = WebflowCollectionItemsPage::getUrl(
            ['collection' => $record?->collection?->id],
            true,
            null,
            Filament::getTenant()
        );

        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('cancel')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
                ->url($this->previousUrl ?: $listUrl)
                ->color('gray'),
            Action::make('pushToWebflow')
                ->label('Push to Webflow')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->action(function () {
                    $this->save(shouldRedirect: false, shouldSendNotification: false);
                    $record = $this->getRecord();
                    if ($record) {
                        PushWebflowItem::dispatch($record, true);
                        Notification::make()
                            ->title('Pushed to Webflow')
                            ->body('Item has been updated and published on Webflow.')
                            ->success()
                            ->send();
                        $user = auth()->user();
                        if ($user) {
                            Notification::make()
                                ->title('Pushed to Webflow')
                                ->body('Item has been updated and published on Webflow.')
                                ->success()
                                ->sendToDatabase($user);
                        }
                    }
                })
                ->visible(fn () => (bool) $this->getRecord()),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendNotification = true): void
    {
        $record = $this->getRecord();
        if (! $record) {
            return;
        }

        $formSchema = $this->getSchema('form');
        $data = $formSchema ? $formSchema->getState() : $this->data;
        $record->field_data = is_array($data) ? $data : [];
        $this->syncMediaUrlsToFieldData($record);
        $record->save();

        if ($shouldSendNotification) {
            Notification::make()
                ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
                ->success()
                ->send();
            $user = auth()->user();
            if ($user) {
                Notification::make()
                    ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
                    ->success()
                    ->sendToDatabase($user);
            }
        }

        if ($shouldRedirect) {
            $url = WebflowCollectionItemsPage::getUrl(
                ['collection' => $record->collection_id],
                true,
                null,
                Filament::getTenant()
            );
            $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
        }
    }

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        $record = $this->getRecord();
        $listUrl = WebflowCollectionItemsPage::getUrl(
            ['collection' => $record?->collection_id],
            true,
            null,
            Filament::getTenant()
        );

        return [
            $listUrl => 'CMS Items',
            'Edit',
        ];
    }

    /**
     * Remove Image/MultiImage keys from form state so SpatieMediaLibraryFileUpload
     * loads from the model's media collections instead of raw field_data (which may be objects).
     */
    protected function stripImageFieldsFromState(): void
    {
        $collection = $this->record?->collection;
        if (! $collection instanceof WebflowCollection) {
            return;
        }
        $schema = $collection->schema ?? [];
        foreach ($schema as $field) {
            $type = $field['type'] ?? null;
            if ($type !== 'Image' && $type !== 'MultiImage') {
                continue;
            }
            $slug = $field['slug'] ?? null;
            if (is_string($slug)) {
                unset($this->data[$slug]);
            }
        }
    }

    /**
     * Sync media collection URLs into field_data for Image/MultiImage slugs so Push to Webflow receives URLs.
     */
    protected function syncMediaUrlsToFieldData(WebflowItem $record): void
    {
        $collection = $record->collection;
        if (! $collection instanceof WebflowCollection) {
            return;
        }
        $schema = $collection->schema ?? [];
        $data = $record->field_data ?? [];
        foreach ($schema as $field) {
            $type = $field['type'] ?? null;
            $slug = $field['slug'] ?? null;
            if (! is_string($slug)) {
                continue;
            }
            if ($type === 'Image') {
                $url = $record->getFirstMediaUrl($slug);
                $data[$slug] = $url !== '' ? $url : ($data[$slug] ?? null);
            } elseif ($type === 'MultiImage') {
                $urls = $record->getMedia($slug)->map(fn ($m) => $m->getUrl())->filter()->values()->all();
                $data[$slug] = $urls;
            }
        }
        $record->field_data = $data;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        $listUrl = WebflowCollectionItemsPage::getUrl(
            ['collection' => $record?->collection_id],
            true,
            null,
            Filament::getTenant()
        );

        return [
            Action::make('back')
                ->label('Back to CMS items')
                ->url($listUrl)
                ->color('gray'),
        ];
    }
}
