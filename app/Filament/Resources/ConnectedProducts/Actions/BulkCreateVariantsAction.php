<?php

namespace App\Filament\Resources\ConnectedProducts\Actions;

use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class BulkCreateVariantsAction
{
    public static function make(): Action
    {
        return Action::make('bulkCreateVariants')
            ->label(__('filament.actions.bulk_create_variants.label'))
            ->icon('heroicon-o-sparkles')
            ->color('success')
            ->form([
                TextInput::make('preview_count')
                    ->label(__('filament.actions.bulk_create_variants.variants_to_create'))
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => $state ? __('filament.actions.bulk_create_variants.preview_text', ['count' => $state]) : __('filament.actions.bulk_create_variants.preview_placeholder'))
                    ->helperText(__('filament.actions.bulk_create_variants.preview_text'))
                    ->columnSpanFull(),

                TextInput::make('options_header')
                    ->label(__('filament.actions.bulk_create_variants.variant_options'))
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn () => __('filament.actions.bulk_create_variants.options_description'))
                    ->helperText(__('filament.actions.bulk_create_variants.options_help'))
                    ->columnSpanFull(),

                Repeater::make('options')
                    ->label(__('filament.actions.bulk_create_variants.options'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament.actions.bulk_create_variants.option_name'))
                            ->placeholder(__('filament.actions.bulk_create_variants.option_name_placeholder'))
                            ->required()
                            ->maxLength(255)
                            ->live()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                // Auto-generate placeholder values based on common option names
                                $name = strtolower($state ?? '');
                                if (str_contains($name, 'size')) {
                                    $set('values', 'Small, Medium, Large, XL');
                                } elseif (str_contains($name, 'color') || str_contains($name, 'colour')) {
                                    $set('values', 'Red, Blue, Green, Black, White');
                                }
                            }),

                        TextInput::make('values')
                            ->label(__('filament.actions.bulk_create_variants.option_values'))
                            ->placeholder(__('filament.actions.bulk_create_variants.option_values_placeholder'))
                            ->required()
                            ->helperText(__('filament.actions.bulk_create_variants.option_values_help'))
                            ->live()
                            ->afterStateUpdated(function ($state, $get, $set) {
                                // Calculate and show preview of combinations
                                $options = $get('../../options') ?? [];
                                $combinations = self::calculateCombinations($options);
                                $set('../../preview_count', count($combinations));
                            }),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->maxItems(3)
                    ->collapsible()
                    ->itemLabel(fn (array $state): string => ($state['name'] ?? __('filament.actions.bulk_create_variants.options')) . ': ' . ($state['values'] ?? __('filament.actions.bulk_create_variants.no_values')))
                    ->addActionLabel(__('filament.actions.bulk_create_variants.add_option'))
                    ->reorderable()
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('section_defaults')
                    ->label(__('filament.actions.bulk_create_variants.default_values'))
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn () => __('filament.actions.bulk_create_variants.default_values_description'))
                    ->helperText(__('filament.actions.bulk_create_variants.default_values_help'))
                    ->columnSpanFull(),

                TextInput::make('default_price')
                    ->label(__('filament.actions.bulk_create_variants.default_price'))
                    ->numeric()
                    ->prefix('NOK')
                    ->helperText(__('filament.actions.bulk_create_variants.default_price_help'))
                    ->default(0)
                    ->required()
                    ->columnSpanFull(),

                Select::make('default_currency')
                    ->label(__('filament.actions.bulk_create_variants.currency'))
                    ->options([
                        'nok' => 'NOK',
                        'usd' => 'USD',
                        'eur' => 'EUR',
                    ])
                    ->default('nok')
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('default_sku_prefix')
                    ->label(__('filament.actions.bulk_create_variants.sku_prefix'))
                    ->placeholder(__('filament.actions.bulk_create_variants.sku_prefix_placeholder'))
                    ->helperText(__('filament.actions.bulk_create_variants.sku_prefix_help'))
                    ->maxLength(50)
                    ->columnSpanFull(),

                TextInput::make('default_inventory_quantity')
                    ->label(__('filament.actions.bulk_create_variants.default_inventory_quantity'))
                    ->numeric()
                    ->minValue(0)
                    ->helperText(__('filament.actions.bulk_create_variants.default_inventory_quantity_help'))
                    ->columnSpanFull(),

                Select::make('default_inventory_policy')
                    ->label(__('filament.actions.bulk_create_variants.inventory_policy'))
                    ->options([
                        'deny' => __('filament.actions.bulk_create_variants.inventory_policy_deny'),
                        'continue' => __('filament.actions.bulk_create_variants.inventory_policy_continue'),
                    ])
                    ->default('deny')
                    ->columnSpanFull(),

                Toggle::make('default_requires_shipping')
                    ->label(__('filament.actions.bulk_create_variants.requires_shipping'))
                    ->default(true)
                    ->columnSpanFull(),

                Toggle::make('default_taxable')
                    ->label(__('filament.actions.bulk_create_variants.taxable'))
                    ->default(true)
                    ->columnSpanFull(),

                Toggle::make('default_active')
                    ->label(__('filament.actions.bulk_create_variants.active'))
                    ->default(true)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, ConnectedProduct $record) {
                $options = $data['options'] ?? [];
                
                if (empty($options)) {
                    Notification::make()
                        ->danger()
                        ->title(__('filament.actions.bulk_create_variants.no_options'))
                        ->body(__('filament.actions.bulk_create_variants.no_options_body'))
                        ->send();
                    return;
                }

                // Calculate all combinations
                $combinations = self::calculateCombinations($options);

                if (empty($combinations)) {
                    Notification::make()
                        ->danger()
                        ->title(__('filament.actions.bulk_create_variants.no_combinations'))
                        ->body(__('filament.actions.bulk_create_variants.no_combinations_body'))
                        ->send();
                    return;
                }

                // Get existing variants to avoid duplicates
                $existingVariants = ProductVariant::where('connected_product_id', $record->id)
                    ->where('stripe_account_id', $record->stripe_account_id)
                    ->get()
                    ->map(function ($variant) {
                        return [
                            'option1_name' => $variant->option1_name,
                            'option1_value' => $variant->option1_value,
                            'option2_name' => $variant->option2_name,
                            'option2_value' => $variant->option2_value,
                            'option3_name' => $variant->option3_name,
                            'option3_value' => $variant->option3_value,
                        ];
                    })
                    ->toArray();

                $created = 0;
                $skipped = 0;
                $priceAmount = (int) round(($data['default_price'] ?? 0) * 100); // Convert to cents

                foreach ($combinations as $combination) {
                    // Check if this combination already exists
                    $exists = false;
                    foreach ($existingVariants as $existing) {
                        if (
                            ($existing['option1_name'] === $combination['option1_name'] && $existing['option1_value'] === $combination['option1_value']) &&
                            ($existing['option2_name'] === $combination['option2_name'] && $existing['option2_value'] === $combination['option2_value']) &&
                            ($existing['option3_name'] === $combination['option3_name'] && $existing['option3_value'] === $combination['option3_value'])
                        ) {
                            $exists = true;
                            break;
                        }
                    }

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    // Generate SKU
                    $sku = null;
                    if (!empty($data['default_sku_prefix'])) {
                        $skuParts = array_filter([
                            $data['default_sku_prefix'],
                            $record->id,
                            $combination['option1_value'] ?? '',
                            $combination['option2_value'] ?? '',
                            $combination['option3_value'] ?? '',
                        ]);
                        $baseSku = implode('-', array_filter($skuParts));
                        
                        // Ensure uniqueness
                        $sku = $baseSku;
                        $counter = 1;
                        while (ProductVariant::where('stripe_account_id', $record->stripe_account_id)
                            ->where('sku', $sku)
                            ->exists()) {
                            $sku = $baseSku . '-' . $counter;
                            $counter++;
                        }
                    }

                    ProductVariant::create([
                        'connected_product_id' => $record->id,
                        'stripe_account_id' => $record->stripe_account_id,
                        'option1_name' => $combination['option1_name'] ?? null,
                        'option1_value' => $combination['option1_value'] ?? null,
                        'option2_name' => $combination['option2_name'] ?? null,
                        'option2_value' => $combination['option2_value'] ?? null,
                        'option3_name' => $combination['option3_name'] ?? null,
                        'option3_value' => $combination['option3_value'] ?? null,
                        'sku' => $sku,
                        'price_amount' => $priceAmount,
                        'currency' => $data['default_currency'] ?? 'nok',
                        'inventory_quantity' => $data['default_inventory_quantity'] ?? null,
                        'inventory_policy' => $data['default_inventory_policy'] ?? 'deny',
                        'requires_shipping' => $data['default_requires_shipping'] ?? true,
                        'taxable' => $data['default_taxable'] ?? true,
                        'active' => $data['default_active'] ?? true,
                    ]);

                    $created++;
                }

                Notification::make()
                    ->success()
                    ->title(__('filament.actions.bulk_create_variants.variants_created'))
                    ->body(__('filament.actions.bulk_create_variants.variants_created_body', ['count' => $created]) . ($skipped > 0 ? ' ' . __('filament.actions.bulk_create_variants.variants_skipped', ['count' => $skipped]) : ''))
                    ->send();
            })
            ->modalHeading(__('filament.actions.bulk_create_variants.heading'))
            ->modalDescription(__('filament.actions.bulk_create_variants.description'))
            ->modalWidth('2xl');
    }

    /**
     * Calculate all combinations from options
     */
    protected static function calculateCombinations(array $options): array
    {
        if (empty($options)) {
            return [];
        }

        // Parse option values
        $parsedOptions = [];
        foreach ($options as $option) {
            $name = trim($option['name'] ?? '');
            $valuesString = trim($option['values'] ?? '');
            
            if (empty($name) || empty($valuesString)) {
                continue;
            }

            // Split by comma and clean up
            $values = array_map('trim', explode(',', $valuesString));
            $values = array_filter($values); // Remove empty values

            if (!empty($values)) {
                $parsedOptions[] = [
                    'name' => $name,
                    'values' => array_values($values),
                ];
            }
        }

        if (empty($parsedOptions)) {
            return [];
        }

        // Generate Cartesian product
        $combinations = [];
        self::generateCartesianProduct($parsedOptions, 0, [], $combinations);

        return $combinations;
    }

    /**
     * Generate Cartesian product recursively
     */
    protected static function generateCartesianProduct(array $options, int $index, array $current, array &$result): void
    {
        if ($index >= count($options)) {
            // We've processed all options, add this combination
            $combination = [
                'option1_name' => null,
                'option1_value' => null,
                'option2_name' => null,
                'option2_value' => null,
                'option3_name' => null,
                'option3_value' => null,
            ];

            foreach ($current as $i => $value) {
                $optionIndex = $i + 1;
                $combination["option{$optionIndex}_name"] = $options[$i]['name'];
                $combination["option{$optionIndex}_value"] = $value;
            }

            $result[] = $combination;
            return;
        }

        // Try each value for current option
        foreach ($options[$index]['values'] as $value) {
            $current[$index] = $value;
            self::generateCartesianProduct($options, $index + 1, $current, $result);
        }
    }
}
