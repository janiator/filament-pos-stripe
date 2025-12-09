<?php

namespace App\Filament\Resources\ConnectedProducts\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\CheckboxList;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use App\Models\Collection;
use Illuminate\Support\Str;

class ConnectedProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Create form - simple layout
                Select::make('stripe_account_id')
                    ->label('Store')
                    ->options(function () {
                        return \App\Models\Store::whereNotNull('stripe_account_id')
                            ->pluck('name', 'stripe_account_id');
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('The store/connected account this product belongs to')
                    ->visibleOn('create'),

                TextInput::make('name')
                    ->label('Product Name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->visibleOn('create'),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(4)
                    ->columnSpanFull()
                    ->visibleOn('create'),

                Grid::make(2)
                    ->schema([
                        Select::make('type')
                            ->label('Product Type')
                            ->options([
                                'service' => 'Service',
                                'good' => 'Good',
                            ])
                            ->default('service')
                            ->visibleOn('create'),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->visibleOn('create'),
                    ])
                    ->visibleOn('create'),

                Toggle::make('shippable')
                    ->label('Shippable')
                    ->default(false)
                    ->visibleOn('create'),

                // Pricing on create
                Grid::make(2)
                    ->schema([
                        TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('kr')
                            ->helperText('Enter the product price')
                            ->visibleOn('create'),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'nok' => 'NOK (kr)',
                                'usd' => 'USD ($)',
                                'eur' => 'EUR (€)',
                                'sek' => 'SEK',
                                'dkk' => 'DKK',
                            ])
                            ->default('nok')
                            ->visibleOn('create'),
                    ])
                    ->visibleOn('create'),

                TextInput::make('compare_at_price_decimal')
                    ->label('Compare at Price')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('kr')
                    ->helperText('Original price before discount (optional)')
                    ->visibleOn('create')
                    ->dehydrated(false)
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state !== null && $state !== '') {
                            $set('compare_at_price_amount', (int) round($state * 100));
                        } else {
                            $set('compare_at_price_amount', null);
                        }
                    }),

                // Product images
                SpatieMediaLibraryFileUpload::make('images')
                    ->label('Product Images')
                    ->collection('images')
                    ->multiple()
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null,
                        '16:9',
                        '4:3',
                        '1:1',
                    ])
                    ->maxFiles(8)
                    ->helperText('Upload product images. These will be synced to Stripe when saved.')
                    ->columnSpanFull()
                    ->visibleOn('create'),

                // Edit form - Shopify-style layout
                Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])
                    ->schema([
                        // Left column - Media (takes 1/3 on large screens)
                        Group::make()
                            ->schema([
                                Section::make('Media')
                                    ->description('Product images and media')
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('images')
                                            ->label('Product Images')
                                            ->collection('images')
                                            ->multiple()
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([
                                                null,
                                                '16:9',
                                                '4:3',
                                                '1:1',
                                            ])
                                            ->maxFiles(8)
                                            ->helperText('Upload product images. These will be synced to Stripe when saved.')
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false),
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 1,
                            ])
                            ->visibleOn('edit'),

                        // Right column - Product Details (takes 2/3 on large screens)
                        Group::make()
                            ->schema([
                                // Product Information Section
                                Section::make('Product Information')
                                    ->description('Basic product details')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Product Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull()
                                            ->helperText('This field will sync to Stripe when saved'),

                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(8)
                                            ->columnSpanFull()
                                            ->helperText('Product description. This field will sync to Stripe when saved'),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('type')
                                                    ->label('Product Type')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Service')
                                                    ->helperText('Product type cannot be changed after creation'),

                                                Toggle::make('active')
                                                    ->label('Product Status')
                                                    ->helperText('Active products are visible in Stripe')
                                                    ->default(true),
                                            ])
                                            ->columnSpanFull(),

                                        Select::make('product_type')
                                            ->label('Product Structure')
                                            ->options([
                                                'single' => 'Single Product (no variants)',
                                                'variable' => 'Variable Product (has variants)',
                                                'auto' => 'Auto-detect from variants',
                                            ])
                                            ->default('auto')
                                            ->helperText('Single: Only main product in Stripe. Variable: Only variants in Stripe (no main product). Auto: Detected from variant count.')
                                            ->formatStateUsing(function ($state, $record) {
                                                if ($state) {
                                                    return $state;
                                                }
                                                if ($record) {
                                                    // Check if manually set in metadata
                                                    $meta = $record->product_meta ?? [];
                                                    if (isset($meta['product_type'])) {
                                                        return $meta['product_type'];
                                                    }
                                                    // Auto-detect
                                                    return $record->isVariable() ? 'variable' : 'single';
                                                }
                                                return 'auto';
                                            })
                                            ->afterStateUpdated(function ($state, $set, $get, $record) {
                                                // Store in metadata
                                                $meta = $get('product_meta') ?? [];
                                                if ($state && $state !== 'auto') {
                                                    $meta['product_type'] = $state;
                                                } else {
                                                    unset($meta['product_type']);
                                                }
                                                $set('product_meta', $meta);
                                            })
                                            ->dehydrated(false)
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false)
                                    ->visibleOn('edit'),

                                // Pricing Section
                                Section::make('Pricing')
                                    ->description('Product pricing information')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('price')
                                                    ->label('Price')
                                                    ->numeric()
                                                    ->step(0.01)
                                                    ->prefix(fn ($record) => $record && $record->currency ? strtoupper($record->currency) : 'kr')
                                                    ->helperText('Enter the product price. This will create or update the Stripe price automatically.')
                                                    ->formatStateUsing(function ($state, $record) {
                                                        // If price is already set, use it
                                                        if ($state) {
                                                            return $state;
                                                        }
                                                        
                                                        if (!$record || !$record->stripe_product_id || !$record->stripe_account_id) {
                                                            return $state;
                                                        }
                                                        
                                                        // Try to load from default_price first
                                                        if ($record->default_price) {
                                                            $defaultPrice = \App\Models\ConnectedPrice::where('stripe_price_id', $record->default_price)
                                                                ->where('stripe_account_id', $record->stripe_account_id)
                                                                ->first();
                                                            
                                                            if ($defaultPrice && $defaultPrice->unit_amount) {
                                                                // Format as decimal number (e.g., 299.00)
                                                                return number_format($defaultPrice->unit_amount / 100, 2, '.', '');
                                                            }
                                                        }
                                                        
                                                        // Fallback: get the first active price for this product
                                                        $activePrice = \App\Models\ConnectedPrice::where('stripe_product_id', $record->stripe_product_id)
                                                            ->where('stripe_account_id', $record->stripe_account_id)
                                                            ->where('active', true)
                                                            ->orderBy('created_at', 'desc')
                                                            ->first();
                                                        
                                                        if ($activePrice && $activePrice->unit_amount) {
                                                            // Format as decimal number (e.g., 299.00)
                                                            return number_format($activePrice->unit_amount / 100, 2, '.', '');
                                                        }
                                                        
                                                        return $state;
                                                    })
                                                    ->dehydrateStateUsing(function ($state) {
                                                        // Convert to string format for storage (handle both , and . as decimal)
                                                        if (!$state) {
                                                            return null;
                                                        }
                                                        // Remove spaces and convert comma to dot
                                                        return str_replace(',', '.', str_replace(' ', '', (string) $state));
                                                    }),

                                                Select::make('currency')
                                                    ->label('Currency')
                                                    ->options([
                                                        'nok' => 'NOK (kr)',
                                                        'usd' => 'USD ($)',
                                                        'eur' => 'EUR (€)',
                                                        'sek' => 'SEK',
                                                        'dkk' => 'DKK',
                                                    ])
                                                    ->formatStateUsing(function ($state, $record) {
                                                        // If currency is already set, use it
                                                        if ($state) {
                                                            return $state;
                                                        }
                                                        
                                                        if (!$record || !$record->stripe_product_id || !$record->stripe_account_id) {
                                                            return $state ?: 'nok';
                                                        }
                                                        
                                                        // Try to load from default_price first
                                                        if ($record->default_price) {
                                                            $defaultPrice = \App\Models\ConnectedPrice::where('stripe_price_id', $record->default_price)
                                                                ->where('stripe_account_id', $record->stripe_account_id)
                                                                ->first();
                                                            
                                                            if ($defaultPrice && $defaultPrice->currency) {
                                                                return $defaultPrice->currency;
                                                            }
                                                        }
                                                        
                                                        // Fallback: get currency from the first active price for this product
                                                        $activePrice = \App\Models\ConnectedPrice::where('stripe_product_id', $record->stripe_product_id)
                                                            ->where('stripe_account_id', $record->stripe_account_id)
                                                            ->where('active', true)
                                                            ->orderBy('created_at', 'desc')
                                                            ->first();
                                                        
                                                        if ($activePrice && $activePrice->currency) {
                                                            return $activePrice->currency;
                                                        }
                                                        
                                                        return $state ?: 'nok';
                                                    })
                                                    ->helperText('Currency for this product'),
                                            ])
                                            ->columnSpanFull(),

                                        TextInput::make('compare_at_price_decimal')
                                            ->label('Compare at Price')
                                            ->numeric()
                                            ->step(0.01)
                                            ->prefix(fn ($record) => $record && $record->currency ? strtoupper($record->currency) : 'kr')
                                            ->helperText('Original price before discount (optional). Shows discount percentage if set.')
                                            ->default(fn ($record) => $record && $record->compare_at_price_amount ? $record->compare_at_price_amount / 100 : null)
                                            ->dehydrated(false)
                                            ->afterStateUpdated(function ($state, $set) {
                                                if ($state !== null && $state !== '') {
                                                    $set('compare_at_price_amount', (int) round($state * 100));
                                                } else {
                                                    $set('compare_at_price_amount', null);
                                                }
                                            }),

                                        TextInput::make('default_price')
                                            ->label('Default Price ID')
                                            ->helperText('Automatically set when price is saved')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->visible(fn ($record) => $record && $record->default_price),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false)
                                    ->visibleOn('edit'),

                                // Shipping Section
                                Section::make('Shipping')
                                    ->description('Shipping and fulfillment settings')
                                    ->schema([
                                        Toggle::make('shippable')
                                            ->label('This is a physical product')
                                            ->helperText('Enable if this product requires shipping')
                                            ->default(false)
                                            ->columnSpanFull(),

                                        KeyValue::make('package_dimensions')
                                            ->label('Package Dimensions')
                                            ->keyLabel('Dimension')
                                            ->valueLabel('Value')
                                            ->helperText('Package dimensions for shipping (height, length, weight, width). This field will sync to Stripe when saved.')
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->visibleOn('edit'),

                                // Collections Section
                                Section::make('Collections')
                                    ->description('Organize products into collections')
                                    ->schema([
                                        CheckboxList::make('collections')
                                            ->label('Collections')
                                            ->relationship(
                                                'collections',
                                                'name',
                                                modifyQueryUsing: function ($query, $get, $record) {
                                                    $stripeAccountId = $record?->stripe_account_id ?? $get('stripe_account_id');
                                                    
                                                    // Clear any existing orderBy clauses from the relationship
                                                    // (the relationship has orderByPivot which causes PostgreSQL DISTINCT issues)
                                                    $query->getQuery()->orders = [];
                                                    
                                                    if ($stripeAccountId) {
                                                        // Select specific columns to avoid PostgreSQL JSON distinct issue
                                                        // Order by name (which is in SELECT) instead of pivot sort_order
                                                        return $query->where('stripe_account_id', $stripeAccountId)
                                                            ->select('collections.id', 'collections.name', 'collections.stripe_account_id')
                                                            ->orderBy('collections.name', 'asc');
                                                    }
                                                    return $query->select('collections.id', 'collections.name', 'collections.stripe_account_id')
                                                        ->orderBy('collections.name', 'asc');
                                                }
                                            )
                                            ->searchable()
                                            ->helperText('Select collections this product belongs to')
                                            ->hintAction(
                                                Action::make('createCollection')
                                                    ->label('Create New Collection')
                                                    ->icon('heroicon-o-plus')
                                                    ->form([
                                                        TextInput::make('name')
                                                            ->label('Collection Name')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(function ($state, $set) {
                                                                if ($state) {
                                                                    $set('handle', Str::slug($state));
                                                                }
                                                            }),
                                                        TextInput::make('handle')
                                                            ->label('Handle (Slug)')
                                                            ->maxLength(255)
                                                            ->helperText('URL-friendly identifier'),
                                                        Textarea::make('description')
                                                            ->label('Description')
                                                            ->rows(3),
                                                        Toggle::make('active')
                                                            ->label('Active')
                                                            ->default(true),
                                                    ])
                                                    ->action(function (array $data, \Filament\Forms\Get $get, \Filament\Forms\Set $set, $record) {
                                                        // Get stripe_account_id from product record or form state
                                                        $stripeAccountId = $record?->stripe_account_id ?? $get('stripe_account_id');
                                                        
                                                        if (!$stripeAccountId) {
                                                            throw new \Exception('Cannot create collection: stripe_account_id is required');
                                                        }
                                                        
                                                        // Get store_id from tenant
                                                        $tenant = \Filament\Facades\Filament::getTenant();
                                                        $storeId = $tenant?->id;
                                                        
                                                        // Create the collection
                                                        $collection = Collection::create([
                                                            'store_id' => $storeId,
                                                            'stripe_account_id' => $stripeAccountId,
                                                            'name' => $data['name'],
                                                            'handle' => $data['handle'] ?? Str::slug($data['name']),
                                                            'description' => $data['description'] ?? null,
                                                            'active' => $data['active'] ?? true,
                                                        ]);
                                                        
                                                        // Add the new collection to the selected collections
                                                        $currentCollections = $get('collections') ?? [];
                                                        if (!is_array($currentCollections)) {
                                                            $currentCollections = [];
                                                        }
                                                        $currentCollections[] = $collection->id;
                                                        $set('collections', array_unique($currentCollections));
                                                    })
                                                    ->successNotificationTitle('Collection created')
                                            )
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(true),

                                // Additional Details Section
                                Section::make('Additional Details')
                                    ->description('Additional product information')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('statement_descriptor')
                                                    ->label('Statement Descriptor')
                                                    ->maxLength(22)
                                                    ->helperText('Appears on customer statements (max 22 characters)'),

                                                TextInput::make('tax_code')
                                                    ->label('Tax Code')
                                                    ->helperText('Stripe tax code ID for tax calculation'),
                                            ])
                                            ->columnSpanFull(),

                                        Grid::make(2)
                                            ->schema([
                                                Select::make('article_group_code')
                                                    ->label('Article Group Code (SAF-T)')
                                                    ->options(\App\Services\SafTCodeMapper::getArticleGroupCodes())
                                                    ->searchable()
                                                    ->helperText('PredefinedBasicID-04: Product category for SAF-T reporting')
                                                    ->placeholder('Select article group'),

                                                TextInput::make('product_code')
                                                    ->label('Product Code (PLU)')
                                                    ->maxLength(50)
                                                    ->helperText('PLU code (BasicType-02)'),
                                            ])
                                            ->columnSpanFull(),

                                        TextInput::make('unit_label')
                                            ->label('Unit Label')
                                            ->helperText('Unit label (e.g., "kg", "lb", "oz")')
                                            ->columnSpanFull(),

                                        TextInput::make('url')
                                            ->label('Product URL')
                                            ->url()
                                            ->helperText('Product URL or link')
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->visibleOn('edit'),

                                // Product Metadata Section (Shopify fields, SEO, etc.)
                                Section::make('Product Metadata')
                                    ->description('Additional metadata and Shopify fields. These sync to Stripe as metadata.')
                                    ->schema([
                                        KeyValue::make('product_meta')
                                            ->label('Product Metadata')
                                            ->keyLabel('Key')
                                            ->valueLabel('Value')
                                            ->helperText('Custom metadata fields. Common Shopify fields: vendor, tags, handle, category. All values sync to Stripe metadata.')
                                            ->columnSpanFull()
                                            ->addable(true)
                                            ->deletable(true)
                                            ->reorderable(false)
                                            ->default([
                                                'source' => 'manual',
                                            ])
                                            ->formatStateUsing(function ($state) {
                                                if (is_array($state)) {
                                                    return $state;
                                                }
                                                if (is_string($state)) {
                                                    return json_decode($state, true) ?? [];
                                                }
                                                return [];
                                            })
                                            ->dehydrateStateUsing(function ($state) {
                                                if (!is_array($state)) {
                                                    return [];
                                                }
                                                // Filter out empty keys/values
                                                return array_filter($state, function ($value, $key) {
                                                    return !empty($key) && $value !== null && $value !== '';
                                                }, ARRAY_FILTER_USE_BOTH);
                                            }),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('product_meta.vendor')
                                                    ->label('Vendor')
                                                    ->maxLength(255)
                                                    ->helperText('Product vendor/brand name')
                                                    ->dehydrated(false)
                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                        $meta = $get('product_meta') ?? [];
                                                        if ($state) {
                                                            $meta['vendor'] = $state;
                                                        } else {
                                                            unset($meta['vendor']);
                                                        }
                                                        $set('product_meta', $meta);
                                                    })
                                                    ->formatStateUsing(fn ($state, $record) => $record?->product_meta['vendor'] ?? null),

                                                TextInput::make('product_meta.tags')
                                                    ->label('Tags')
                                                    ->maxLength(255)
                                                    ->helperText('Comma-separated tags (e.g., "Golf, Equipment, Titleist")')
                                                    ->dehydrated(false)
                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                        $meta = $get('product_meta') ?? [];
                                                        if ($state) {
                                                            $meta['tags'] = $state;
                                                        } else {
                                                            unset($meta['tags']);
                                                        }
                                                        $set('product_meta', $meta);
                                                    })
                                                    ->formatStateUsing(fn ($state, $record) => $record?->product_meta['tags'] ?? null),
                                            ])
                                            ->columnSpanFull(),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('product_meta.handle')
                                                    ->label('Handle (Slug)')
                                                    ->maxLength(255)
                                                    ->helperText('URL-friendly product handle (e.g., "golf-club-set")')
                                                    ->dehydrated(false)
                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                        $meta = $get('product_meta') ?? [];
                                                        if ($state) {
                                                            $meta['handle'] = $state;
                                                        } else {
                                                            unset($meta['handle']);
                                                        }
                                                        $set('product_meta', $meta);
                                                    })
                                                    ->formatStateUsing(fn ($state, $record) => $record?->product_meta['handle'] ?? null),

                                                TextInput::make('product_meta.category')
                                                    ->label('Category')
                                                    ->maxLength(255)
                                                    ->helperText('Product category (e.g., "Sporting Goods > Golf > Clubs")')
                                                    ->dehydrated(false)
                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                        $meta = $get('product_meta') ?? [];
                                                        if ($state) {
                                                            $meta['category'] = $state;
                                                        } else {
                                                            unset($meta['category']);
                                                        }
                                                        $set('product_meta', $meta);
                                                    })
                                                    ->formatStateUsing(fn ($state, $record) => $record?->product_meta['category'] ?? null),
                                            ])
                                            ->columnSpanFull(),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('product_meta.seo_title')
                                                    ->label('SEO Title')
                                                    ->maxLength(255)
                                                    ->helperText('SEO meta title')
                                                    ->dehydrated(false)
                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                        $meta = $get('product_meta') ?? [];
                                                        if ($state) {
                                                            $meta['seo_title'] = $state;
                                                        } else {
                                                            unset($meta['seo_title']);
                                                        }
                                                        $set('product_meta', $meta);
                                                    })
                                                    ->formatStateUsing(fn ($state, $record) => $record?->product_meta['seo_title'] ?? null),

                                                Textarea::make('product_meta.seo_description')
                                                    ->label('SEO Description')
                                                    ->maxLength(320)
                                                    ->rows(3)
                                                    ->helperText('SEO meta description (max 320 characters)')
                                                    ->dehydrated(false)
                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                        $meta = $get('product_meta') ?? [];
                                                        if ($state) {
                                                            $meta['seo_description'] = $state;
                                                        } else {
                                                            unset($meta['seo_description']);
                                                        }
                                                        $set('product_meta', $meta);
                                                    })
                                                    ->formatStateUsing(fn ($state, $record) => $record?->product_meta['seo_description'] ?? null),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->visibleOn('edit'),

                                // System Information Section
                                Section::make('System Information')
                                    ->description('Technical details')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('stripe_product_id')
                                                    ->label('Stripe Product ID')
                                                    ->disabled()
                                                    ->dehydrated(false),

                                                TextInput::make('stripe_account_id')
                                                    ->label('Stripe Account ID')
                                                    ->disabled()
                                                    ->dehydrated(false),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->visibleOn('edit'),
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 2,
                            ])
                            ->visibleOn('edit'),
                    ])
                    ->columnSpanFull()
                    ->visibleOn('edit'),
            ]);
    }
}
