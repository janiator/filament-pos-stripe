<?php

namespace Positiv\FilamentWebflow\Support;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Positiv\FilamentWebflow\Models\WebflowCollection;

class WebflowSchemaFormBuilder
{
    /**
     * Build form components from Webflow collection schema for CMS-like editing.
     *
     * @return array<Component>
     */
    public static function build(WebflowCollection $collection): array
    {
        $schema = static::orderSchemaFields($collection->schema ?? []);
        if (empty($schema)) {
            return [];
        }

        $fields = [];
        foreach ($schema as $field) {
            $slug = $field['slug'] ?? null;
            if (! $slug) {
                continue;
            }
            $label = $field['displayName'] ?? $slug;
            $type = $field['type'] ?? 'PlainText';
            $required = (bool) ($field['required'] ?? false);

            $component = static::componentForField($slug, $label, $type, $field);
            if ($component) {
                if ($required) {
                    $component = $component->required();
                }
                $fields[] = $component;
            }
        }

        if (empty($fields)) {
            return [];
        }

        return [
            Section::make('Content')
                ->description('Edit the CMS item fields. Changes are saved locally; use "Push to Webflow" to sync to the live site.')
                ->schema($fields)
                ->columns(1)
                ->collapsible(),
        ];
    }

    /**
     * @param  array<string, mixed>  $fieldDef
     */
    protected static function componentForField(string $slug, string $label, string $type, array $fieldDef): ?Component
    {
        return match ($type) {
            'RichText' => RichEditor::make($slug)
                ->label($label)
                ->columnSpanFull(),
            'Number' => TextInput::make($slug)
                ->label($label)
                ->numeric(),
            'Switch' => Toggle::make($slug)
                ->label($label),
            'DateTime' => DateTimePicker::make($slug)
                ->label($label)
                ->native(false),
            'Email' => TextInput::make($slug)
                ->label($label)
                ->email(),
            'Phone' => TextInput::make($slug)
                ->label($label)
                ->tel(),
            'Link', 'VideoLink' => TextInput::make($slug)
                ->label($label)
                ->url(),
            'Option' => static::optionSelect($slug, $label, $fieldDef),
            'Image' => SpatieMediaLibraryFileUpload::make($slug)
                ->label($label)
                ->collection($slug)
                ->image()
                ->columnSpanFull(),
            'MultiImage' => SpatieMediaLibraryFileUpload::make($slug)
                ->label($label)
                ->collection($slug)
                ->multiple()
                ->image()
                ->columnSpanFull(),
            'PlainText', 'File', 'Color', 'Reference', 'MultiReference' => TextInput::make($slug)
                ->label($label)
                ->columnSpanFull(),
            default => TextInput::make($slug)
                ->label($label)
                ->columnSpanFull(),
        };
    }

    /**
     * @param  array<string, mixed>  $fieldDef
     */
    protected static function optionSelect(string $slug, string $label, array $fieldDef): Select
    {
        $options = $fieldDef['options'] ?? $fieldDef['metadata']['options'] ?? [];
        $selectOptions = [];
        if (is_array($options)) {
            foreach ($options as $opt) {
                $id = $opt['id'] ?? $opt['value'] ?? null;
                $name = $opt['name'] ?? $opt['label'] ?? (string) $id;
                if ($id !== null) {
                    $selectOptions[(string) $id] = $name;
                }
            }
        }

        $select = Select::make($slug)->label($label);
        if (! empty($selectOptions)) {
            $select->options($selectOptions)->searchable();
        }

        return $select;
    }

    /**
     * Order schema fields: name, slug, description first, then the rest.
     *
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, array<string, mixed>>
     */
    public static function orderSchemaFields(array $schema): array
    {
        $order = ['name' => 0, 'slug' => 1, 'description' => 2];
        usort($schema, function (array $a, array $b) use ($order): int {
            $slugA = $a['slug'] ?? '';
            $slugB = $b['slug'] ?? '';
            $posA = $order[$slugA] ?? 999;
            $posB = $order[$slugB] ?? 999;

            return $posA <=> $posB;
        });

        return $schema;
    }
}
