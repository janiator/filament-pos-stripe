<?php

namespace App\Filament\Resources\ConnectedProducts\Schemas;

use App\Models\ArticleGroupCode;
use App\Models\Collection;
use App\Models\Vendor;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ConnectedProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Create form - simple layout
                // Store field is hidden on create and edit - automatically set from current tenant
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
                    ->hiddenOn(['create', 'edit']),

                // Create form - matching edit form structure
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
                            ->visibleOn('create'),

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
                                                Select::make('type')
                                                    ->label('Product Type')
                                                    ->options([
                                                        'service' => 'Service',
                                                        'good' => 'Good',
                                                    ])
                                                    ->default('service')
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
                                            ->afterStateUpdated(function ($state, $set, $get) {
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
                                    ->visibleOn('create'),

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
                                                    ->prefix('kr')
                                                    ->helperText('Enter the product price. This will create or update the Stripe price automatically.')
                                                    ->dehydrateStateUsing(function ($state) {
                                                        // Convert to string format for storage (handle both , and . as decimal)
                                                        if (! $state) {
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
                                                    ->default('nok')
                                                    ->helperText('Currency for this product'),
                                            ])
                                            ->columnSpanFull(),

                                        TextInput::make('compare_at_price_decimal')
                                            ->label('Compare at Price')
                                            ->numeric()
                                            ->step(0.01)
                                            ->prefix('kr')
                                            ->helperText('Original price before discount (optional). Shows discount percentage if set.')
                                            ->dehydrated(false)
                                            ->afterStateUpdated(function ($state, $set) {
                                                if ($state !== null && $state !== '') {
                                                    $set('compare_at_price_amount', (int) round($state * 100));
                                                } else {
                                                    $set('compare_at_price_amount', null);
                                                }
                                            }),

                                        Toggle::make('no_price_in_pos')
                                            ->label('No Price in POS')
                                            ->helperText('Enable this to allow custom price input on POS. When enabled, the price field can be left empty and will not be restored from default_price.')
                                            ->default(false)
                                            ->columnSpanFull(),

                                        Grid::make(2)
                                            ->schema([
                                                self::buildArticleGroupCodeSelect(),
                                                self::buildVatPercentInputForCreate(),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false)
                                    ->visibleOn('create'),

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
                                    ->visibleOn('create'),

                                // Vendor Section
                                Section::make('Vendor')
                                    ->description('Assign a vendor to this product')
                                    ->schema([
                                        Select::make('vendor_id')
                                            ->label('Vendor')
                                            ->relationship(
                                                'vendor',
                                                'name',
                                                modifyQueryUsing: function ($query, $get) {
                                                    // Prioritize tenant's stripe_account_id (most reliable for preload)
                                                    $stripeAccountId = null;

                                                    try {
                                                        $tenant = \Filament\Facades\Filament::getTenant();
                                                        $stripeAccountId = $tenant?->stripe_account_id;
                                                    } catch (\Throwable $e) {
                                                        // Fallback if Filament facade not available
                                                    }

                                                    // Fallback to form state if tenant not available
                                                    if (! $stripeAccountId) {
                                                        $stripeAccountId = $get('stripe_account_id');
                                                    }

                                                    if ($stripeAccountId) {
                                                        return $query->where('stripe_account_id', $stripeAccountId)
                                                            ->where('active', true)
                                                            ->orderBy('name', 'asc');
                                                    }

                                                    // If no stripe_account_id, return empty query for safety
                                                    return $query->whereRaw('1 = 0');
                                                }
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Select the vendor for this product')
                                            ->placeholder('No vendor')
                                            ->hintAction(
                                                Action::make('createVendor')
                                                    ->label('Create New Vendor')
                                                    ->icon('heroicon-o-plus')
                                                    ->form([
                                                        TextInput::make('name')
                                                            ->label('Vendor Name')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->helperText('The name of the vendor'),
                                                        Textarea::make('description')
                                                            ->label('Description')
                                                            ->rows(3)
                                                            ->helperText('Optional description for this vendor'),
                                                        TextInput::make('contact_email')
                                                            ->label('Contact Email')
                                                            ->email()
                                                            ->maxLength(255)
                                                            ->helperText('Contact email address for this vendor'),
                                                        TextInput::make('contact_phone')
                                                            ->label('Contact Phone')
                                                            ->tel()
                                                            ->maxLength(255)
                                                            ->helperText('Contact phone number for this vendor'),
                                                        Toggle::make('active')
                                                            ->label('Active')
                                                            ->default(true)
                                                            ->helperText('Only active vendors are visible'),
                                                    ])
                                                    ->action(function (array $data, \Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                                                        // Get stripe_account_id from form state
                                                        $stripeAccountId = $get('stripe_account_id');

                                                        if (! $stripeAccountId) {
                                                            throw new \Exception('Cannot create vendor: stripe_account_id is required');
                                                        }

                                                        // Get store_id from tenant
                                                        $tenant = \Filament\Facades\Filament::getTenant();
                                                        $storeId = $tenant?->id;

                                                        if (! $storeId) {
                                                            throw new \Exception('Cannot create vendor: store_id is required');
                                                        }

                                                        // Create the vendor
                                                        $vendor = Vendor::create([
                                                            'store_id' => $storeId,
                                                            'stripe_account_id' => $stripeAccountId,
                                                            'name' => $data['name'],
                                                            'description' => $data['description'] ?? null,
                                                            'contact_email' => $data['contact_email'] ?? null,
                                                            'contact_phone' => $data['contact_phone'] ?? null,
                                                            'active' => $data['active'] ?? true,
                                                        ]);

                                                        // Set the new vendor as selected
                                                        $set('vendor_id', $vendor->id);
                                                    })
                                                    ->successNotificationTitle('Vendor created')
                                            )
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false)
                                    ->visibleOn('create'),

                                // Collections Section
                                Section::make('Collections')
                                    ->description('Organize products into collections')
                                    ->schema([
                                        CheckboxList::make('collections')
                                            ->label('Collections')
                                            ->relationship(
                                                'collections',
                                                'name',
                                                modifyQueryUsing: function ($query, $get) {
                                                    $stripeAccountId = $get('stripe_account_id');

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
                                                    ->action(function (array $data, \Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                                                        // Get stripe_account_id from form state
                                                        $stripeAccountId = $get('stripe_account_id');

                                                        if (! $stripeAccountId) {
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
                                                        if (! is_array($currentCollections)) {
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
                                    ->collapsed(true)
                                    ->visibleOn('create'),

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

                                            ])
                                            ->columnSpanFull(),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('product_code')
                                                    ->label('Product Code (PLU)')
                                                    ->maxLength(50)
                                                    ->helperText('PLU code (BasicType-02)'),
                                            ])
                                            ->columnSpanFull(),

                                        Select::make('quantity_unit_id')
                                            ->label('Quantity Unit')
                                            ->relationship(
                                                'quantityUnit',
                                                'name',
                                                modifyQueryUsing: function ($query, $get) {
                                                    // Prioritize tenant's stripe_account_id (most reliable for preload)
                                                    $stripeAccountId = null;

                                                    try {
                                                        $tenant = \Filament\Facades\Filament::getTenant();
                                                        $stripeAccountId = $tenant?->stripe_account_id;
                                                    } catch (\Throwable $e) {
                                                        // Fallback if Filament facade not available
                                                    }

                                                    // Fallback to form state if tenant not available
                                                    if (! $stripeAccountId) {
                                                        $stripeAccountId = $get('stripe_account_id');
                                                    }

                                                    // Include store-specific units first, then global standard units as fallback
                                                    // Only show global units if they don't have a store-specific version
                                                    if ($stripeAccountId) {
                                                        return $query->where(function ($q) use ($stripeAccountId) {
                                                            $q->where('stripe_account_id', $stripeAccountId)
                                                                ->orWhere(function ($q2) use ($stripeAccountId) {
                                                                    // Only include global units that don't have a store-specific version
                                                                    $q2->whereNull('stripe_account_id')
                                                                        ->where('is_standard', true)
                                                                        ->whereNotExists(function ($subQuery) use ($stripeAccountId) {
                                                                            $subQuery->select(\DB::raw(1))
                                                                                ->from('quantity_units as q2')
                                                                                ->whereColumn('q2.name', 'quantity_units.name')
                                                                                ->where(function ($q3) {
                                                                                    $q3->whereColumn('q2.symbol', 'quantity_units.symbol')
                                                                                        ->orWhere(function ($q4) {
                                                                                            $q4->whereNull('q2.symbol')
                                                                                                ->whereNull('quantity_units.symbol');
                                                                                        });
                                                                                })
                                                                                ->where('q2.stripe_account_id', $stripeAccountId)
                                                                                ->where('q2.active', true);
                                                                        });
                                                                });
                                                        })
                                                            ->where('active', true)
                                                            ->orderByRaw('CASE WHEN stripe_account_id IS NOT NULL THEN 0 ELSE 1 END')
                                                            ->orderBy('name', 'asc');
                                                    }

                                                    // If no stripe_account_id, return global standard units
                                                    return $query->whereNull('stripe_account_id')
                                                        ->where('is_standard', true)
                                                        ->where('active', true)
                                                        ->orderBy('name', 'asc');
                                                }
                                            )
                                            ->getOptionLabelFromRecordUsing(function ($record) {
                                                return $record->name.($record->symbol ? ' ('.$record->symbol.')' : '');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->default(function ($get) {
                                                // Default to "Piece" (stk) for new products
                                                $stripeAccountId = null;
                                                try {
                                                    $tenant = \Filament\Facades\Filament::getTenant();
                                                    $stripeAccountId = $tenant?->stripe_account_id;
                                                } catch (\Throwable $e) {
                                                    // Fallback
                                                }

                                                if (! $stripeAccountId) {
                                                    $stripeAccountId = $get('stripe_account_id');
                                                }

                                                // Find "Piece" quantity unit - prioritize by stripe_account_id, then fallback to standard
                                                $pieceUnit = null;

                                                // First try to find store-specific Piece unit
                                                if ($stripeAccountId) {
                                                    $pieceUnit = \App\Models\QuantityUnit::where('stripe_account_id', $stripeAccountId)
                                                        ->where('name', 'Piece')
                                                        ->where('active', true)
                                                        ->first();
                                                }

                                                // Fallback to standard Piece unit if not found
                                                if (! $pieceUnit) {
                                                    $pieceUnit = \App\Models\QuantityUnit::whereNull('stripe_account_id')
                                                        ->where('is_standard', true)
                                                        ->where('name', 'Piece')
                                                        ->where('active', true)
                                                        ->first();
                                                }

                                                // Last resort: find any Piece unit (by name or symbol)
                                                if (! $pieceUnit) {
                                                    $pieceUnit = \App\Models\QuantityUnit::where(function ($q) {
                                                        $q->where('name', 'Piece')
                                                            ->orWhere('symbol', 'stk');
                                                    })
                                                        ->where('active', true)
                                                        ->first();
                                                }

                                                return $pieceUnit?->id;
                                            })
                                            ->helperText('Select the quantity unit for this product (e.g., per piece, per kg, per meter). This determines how the price is calculated. Defaults to Piece (stk).')
                                            ->placeholder('Select quantity unit')
                                            ->hintAction(
                                                Action::make('createQuantityUnit')
                                                    ->label('Create New Quantity Unit')
                                                    ->icon('heroicon-o-plus')
                                                    ->form([
                                                        TextInput::make('name')
                                                            ->label('Name')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->helperText('The name of the quantity unit (e.g., "Piece", "Kilogram")'),
                                                        TextInput::make('symbol')
                                                            ->label('Symbol')
                                                            ->maxLength(20)
                                                            ->helperText('The symbol or abbreviation (e.g., "stk", "kg")'),
                                                        Textarea::make('description')
                                                            ->label('Description')
                                                            ->rows(3)
                                                            ->helperText('Optional description for this quantity unit'),
                                                        Toggle::make('active')
                                                            ->label('Active')
                                                            ->default(true),
                                                    ])
                                                    ->action(function (array $data, \Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                                                        // Get stripe_account_id from form state
                                                        $stripeAccountId = $get('stripe_account_id');

                                                        if (! $stripeAccountId) {
                                                            throw new \Exception('Cannot create quantity unit: stripe_account_id is required');
                                                        }

                                                        // Get store_id from tenant
                                                        $tenant = \Filament\Facades\Filament::getTenant();
                                                        $storeId = $tenant?->id;

                                                        if (! $storeId) {
                                                            throw new \Exception('Cannot create quantity unit: store_id is required');
                                                        }

                                                        // Create the quantity unit
                                                        $quantityUnit = \App\Models\QuantityUnit::create([
                                                            'store_id' => $storeId,
                                                            'stripe_account_id' => $stripeAccountId,
                                                            'name' => $data['name'],
                                                            'symbol' => $data['symbol'] ?? null,
                                                            'description' => $data['description'] ?? null,
                                                            'active' => $data['active'] ?? true,
                                                            'is_standard' => false,
                                                        ]);

                                                        // Set the new quantity unit as selected
                                                        $set('quantity_unit_id', $quantityUnit->id);
                                                    })
                                                    ->successNotificationTitle('Quantity unit created')
                                            )
                                            ->columnSpanFull()
                                            ->helperText('Unit label will be automatically set from the quantity unit symbol.')
                                            ->columnSpanFull(),

                                        TextInput::make('url')
                                            ->label('Product URL')
                                            ->url()
                                            ->helperText('Product URL or link')
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->visibleOn('create'),

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
                                                if (! is_array($state)) {
                                                    return [];
                                                }

                                                // Filter out empty keys/values
                                                return array_filter($state, function ($value, $key) {
                                                    return ! empty($key) && $value !== null && $value !== '';
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
                                                    }),

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
                                                    }),
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
                                                    }),

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
                                                    }),
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
                                                    }),

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
                                                    }),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->visibleOn('create'),
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 2,
                            ])
                            ->visibleOn('create'),
                    ])
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
                                                    ->helperText('Enter the product price (source of truth). Saving will create or update the Stripe price.')
                                                    ->formatStateUsing(function ($state, $record) {
                                                        // Prefer stored price column (Filament source of truth); fall back to Stripe only when empty
                                                        $stored = $record ? $record->getRawOriginal('price') : null;
                                                        if ($stored !== null && $stored !== '') {
                                                            return str_replace(',', '.', (string) $stored);
                                                        }

                                                        if (! $record || ! $record->stripe_product_id || ! $record->stripe_account_id) {
                                                            return $state;
                                                        }

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
                                                        if (! $state) {
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
                                                        // Prefer stored currency column (Filament source of truth); fall back to Stripe only when empty
                                                        $stored = $record ? $record->getRawOriginal('currency') : null;
                                                        if ($stored !== null && $stored !== '') {
                                                            return $stored;
                                                        }

                                                        if (! $record || ! $record->stripe_product_id || ! $record->stripe_account_id) {
                                                            return $state ?: 'nok';
                                                        }

                                                        if ($record->default_price) {
                                                            $defaultPrice = \App\Models\ConnectedPrice::where('stripe_price_id', $record->default_price)
                                                                ->where('stripe_account_id', $record->stripe_account_id)
                                                                ->first();

                                                            if ($defaultPrice && $defaultPrice->currency) {
                                                                return $defaultPrice->currency;
                                                            }
                                                        }

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

                                        Toggle::make('no_price_in_pos')
                                            ->label('No Price in POS')
                                            ->helperText('Enable this to allow custom price input on POS. When enabled, the price field can be left empty and will not be restored from default_price.')
                                            ->default(false)
                                            ->columnSpanFull(),

                                        Grid::make(2)
                                            ->schema([
                                                self::buildArticleGroupCodeSelect(),
                                                self::buildVatPercentInputForEdit(),
                                            ])
                                            ->columnSpanFull(),
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

                                // Vendor Section
                                Section::make('Vendor')
                                    ->description('Assign a vendor to this product')
                                    ->schema([
                                        Select::make('vendor_id')
                                            ->label('Vendor')
                                            ->relationship(
                                                'vendor',
                                                'name',
                                                modifyQueryUsing: function ($query, $get, $record) {
                                                    $stripeAccountId = self::resolveStripeAccountId($get, $record);

                                                    if ($stripeAccountId) {
                                                        return $query->where('stripe_account_id', $stripeAccountId)
                                                            ->where('active', true)
                                                            ->orderBy('name', 'asc');
                                                    }

                                                    return $query->whereRaw('1 = 0');
                                                }
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Select the vendor for this product')
                                            ->placeholder('No vendor')
                                            ->hintAction(
                                                Action::make('createVendor')
                                                    ->label('Create New Vendor')
                                                    ->icon('heroicon-o-plus')
                                                    ->form([
                                                        TextInput::make('name')
                                                            ->label('Vendor Name')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->helperText('The name of the vendor'),
                                                        Textarea::make('description')
                                                            ->label('Description')
                                                            ->rows(3)
                                                            ->helperText('Optional description for this vendor'),
                                                        TextInput::make('contact_email')
                                                            ->label('Contact Email')
                                                            ->email()
                                                            ->maxLength(255)
                                                            ->helperText('Contact email address for this vendor'),
                                                        TextInput::make('contact_phone')
                                                            ->label('Contact Phone')
                                                            ->tel()
                                                            ->maxLength(255)
                                                            ->helperText('Contact phone number for this vendor'),
                                                        Toggle::make('active')
                                                            ->label('Active')
                                                            ->default(true)
                                                            ->helperText('Only active vendors are visible'),
                                                    ])
                                                    ->action(function (array $data, \Filament\Forms\Get $get, \Filament\Forms\Set $set, $record) {
                                                        // Get stripe_account_id from product record or form state
                                                        $stripeAccountId = $record?->stripe_account_id ?? $get('stripe_account_id');

                                                        if (! $stripeAccountId) {
                                                            throw new \Exception('Cannot create vendor: stripe_account_id is required');
                                                        }

                                                        // Get store_id from tenant
                                                        $tenant = \Filament\Facades\Filament::getTenant();
                                                        $storeId = $tenant?->id;

                                                        if (! $storeId) {
                                                            throw new \Exception('Cannot create vendor: store_id is required');
                                                        }

                                                        // Create the vendor
                                                        $vendor = Vendor::create([
                                                            'store_id' => $storeId,
                                                            'stripe_account_id' => $stripeAccountId,
                                                            'name' => $data['name'],
                                                            'description' => $data['description'] ?? null,
                                                            'contact_email' => $data['contact_email'] ?? null,
                                                            'contact_phone' => $data['contact_phone'] ?? null,
                                                            'active' => $data['active'] ?? true,
                                                        ]);

                                                        // Set the new vendor as selected
                                                        $set('vendor_id', $vendor->id);
                                                    })
                                                    ->successNotificationTitle('Vendor created')
                                            )
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false)
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

                                                        if (! $stripeAccountId) {
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
                                                        if (! is_array($currentCollections)) {
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

                                            ])
                                            ->columnSpanFull(),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('product_code')
                                                    ->label('Product Code (PLU)')
                                                    ->maxLength(50)
                                                    ->helperText('PLU code (BasicType-02)'),
                                            ])
                                            ->columnSpanFull(),

                                        Select::make('quantity_unit_id')
                                            ->label('Quantity Unit')
                                            ->relationship(
                                                'quantityUnit',
                                                'name',
                                                modifyQueryUsing: function ($query, $get, $record) {
                                                    // Prioritize tenant's stripe_account_id (most reliable for preload)
                                                    $stripeAccountId = null;

                                                    try {
                                                        $tenant = \Filament\Facades\Filament::getTenant();
                                                        $stripeAccountId = $tenant?->stripe_account_id;
                                                    } catch (\Throwable $e) {
                                                        // Fallback if Filament facade not available
                                                    }

                                                    // Fallback to record or form state if tenant not available
                                                    if (! $stripeAccountId) {
                                                        $stripeAccountId = $record?->stripe_account_id ?? $get('stripe_account_id');
                                                    }

                                                    // Include store-specific units first, then global standard units as fallback
                                                    // Only show global units if they don't have a store-specific version
                                                    if ($stripeAccountId) {
                                                        return $query->where(function ($q) use ($stripeAccountId) {
                                                            $q->where('stripe_account_id', $stripeAccountId)
                                                                ->orWhere(function ($q2) use ($stripeAccountId) {
                                                                    // Only include global units that don't have a store-specific version
                                                                    $q2->whereNull('stripe_account_id')
                                                                        ->where('is_standard', true)
                                                                        ->whereNotExists(function ($subQuery) use ($stripeAccountId) {
                                                                            $subQuery->select(\DB::raw(1))
                                                                                ->from('quantity_units as q2')
                                                                                ->whereColumn('q2.name', 'quantity_units.name')
                                                                                ->where(function ($q3) {
                                                                                    $q3->whereColumn('q2.symbol', 'quantity_units.symbol')
                                                                                        ->orWhere(function ($q4) {
                                                                                            $q4->whereNull('q2.symbol')
                                                                                                ->whereNull('quantity_units.symbol');
                                                                                        });
                                                                                })
                                                                                ->where('q2.stripe_account_id', $stripeAccountId)
                                                                                ->where('q2.active', true);
                                                                        });
                                                                });
                                                        })
                                                            ->where('active', true)
                                                            ->orderByRaw('CASE WHEN stripe_account_id IS NOT NULL THEN 0 ELSE 1 END')
                                                            ->orderBy('name', 'asc');
                                                    }

                                                    // If no stripe_account_id, return global standard units
                                                    return $query->whereNull('stripe_account_id')
                                                        ->where('is_standard', true)
                                                        ->where('active', true)
                                                        ->orderBy('name', 'asc');
                                                }
                                            )
                                            ->getOptionLabelFromRecordUsing(function ($record) {
                                                return $record->name.($record->symbol ? ' ('.$record->symbol.')' : '');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->default(function ($get, $record) {
                                                // If editing and already has a quantity unit, use it
                                                if ($record && $record->quantity_unit_id) {
                                                    return $record->quantity_unit_id;
                                                }

                                                // Default to "Piece" (stk) for new products
                                                $stripeAccountId = null;
                                                try {
                                                    $tenant = \Filament\Facades\Filament::getTenant();
                                                    $stripeAccountId = $tenant?->stripe_account_id;
                                                } catch (\Throwable $e) {
                                                    // Fallback
                                                }

                                                if (! $stripeAccountId) {
                                                    $stripeAccountId = $record?->stripe_account_id ?? $get('stripe_account_id');
                                                }

                                                // Find "Piece" quantity unit - prioritize by stripe_account_id, then fallback to standard
                                                $pieceUnit = null;

                                                // First try to find store-specific Piece unit
                                                if ($stripeAccountId) {
                                                    $pieceUnit = \App\Models\QuantityUnit::where('stripe_account_id', $stripeAccountId)
                                                        ->where('name', 'Piece')
                                                        ->where('active', true)
                                                        ->first();
                                                }

                                                // Fallback to standard Piece unit if not found
                                                if (! $pieceUnit) {
                                                    $pieceUnit = \App\Models\QuantityUnit::whereNull('stripe_account_id')
                                                        ->where('is_standard', true)
                                                        ->where('name', 'Piece')
                                                        ->where('active', true)
                                                        ->first();
                                                }

                                                // Last resort: find any Piece unit (by name or symbol)
                                                if (! $pieceUnit) {
                                                    $pieceUnit = \App\Models\QuantityUnit::where(function ($q) {
                                                        $q->where('name', 'Piece')
                                                            ->orWhere('symbol', 'stk');
                                                    })
                                                        ->where('active', true)
                                                        ->first();
                                                }

                                                return $pieceUnit?->id;
                                            })
                                            ->helperText('Select the quantity unit for this product (e.g., per piece, per kg, per meter). This determines how the price is calculated. Defaults to Piece (stk).')
                                            ->placeholder('Select quantity unit')
                                            ->hintAction(
                                                Action::make('createQuantityUnit')
                                                    ->label('Create New Quantity Unit')
                                                    ->icon('heroicon-o-plus')
                                                    ->form([
                                                        TextInput::make('name')
                                                            ->label('Name')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->helperText('The name of the quantity unit (e.g., "Piece", "Kilogram")'),
                                                        TextInput::make('symbol')
                                                            ->label('Symbol')
                                                            ->maxLength(20)
                                                            ->helperText('The symbol or abbreviation (e.g., "stk", "kg")'),
                                                        Textarea::make('description')
                                                            ->label('Description')
                                                            ->rows(3)
                                                            ->helperText('Optional description for this quantity unit'),
                                                        Toggle::make('active')
                                                            ->label('Active')
                                                            ->default(true),
                                                    ])
                                                    ->action(function (array $data, \Filament\Forms\Get $get, \Filament\Forms\Set $set, $record) {
                                                        // Get stripe_account_id from product record or form state
                                                        $stripeAccountId = $record?->stripe_account_id ?? $get('stripe_account_id');

                                                        if (! $stripeAccountId) {
                                                            throw new \Exception('Cannot create quantity unit: stripe_account_id is required');
                                                        }

                                                        // Get store_id from tenant
                                                        $tenant = \Filament\Facades\Filament::getTenant();
                                                        $storeId = $tenant?->id;

                                                        if (! $storeId) {
                                                            throw new \Exception('Cannot create quantity unit: store_id is required');
                                                        }

                                                        // Create the quantity unit
                                                        $quantityUnit = \App\Models\QuantityUnit::create([
                                                            'store_id' => $storeId,
                                                            'stripe_account_id' => $stripeAccountId,
                                                            'name' => $data['name'],
                                                            'symbol' => $data['symbol'] ?? null,
                                                            'description' => $data['description'] ?? null,
                                                            'active' => $data['active'] ?? true,
                                                            'is_standard' => false,
                                                        ]);

                                                        // Set the new quantity unit as selected
                                                        $set('quantity_unit_id', $quantityUnit->id);
                                                    })
                                                    ->successNotificationTitle('Quantity unit created')
                                            )
                                            ->columnSpanFull()
                                            ->helperText('Unit label will be automatically set from the quantity unit symbol.')
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
                                                if (! is_array($state)) {
                                                    return [];
                                                }

                                                // Filter out empty keys/values
                                                return array_filter($state, function ($value, $key) {
                                                    return ! empty($key) && $value !== null && $value !== '';
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

    /**
     * Get the stripe_account_id from tenant or form state.
     */
    private static function resolveStripeAccountId(Get $get, mixed $record = null): ?string
    {
        $stripeAccountId = null;
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            $stripeAccountId = $tenant?->stripe_account_id;
        } catch (\Throwable $e) {
            // Fallback
        }

        if (! $stripeAccountId) {
            $stripeAccountId = $record?->stripe_account_id ?? $get('stripe_account_id');
        }

        return $stripeAccountId;
    }

    /**
     * Build the article group code query with store-specific and global standard codes.
     */
    private static function buildArticleGroupCodeQuery(?string $stripeAccountId): \Illuminate\Database\Eloquent\Builder
    {
        $query = ArticleGroupCode::query();

        if ($stripeAccountId) {
            $query->where(function ($q) use ($stripeAccountId) {
                $q->where('stripe_account_id', $stripeAccountId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('stripe_account_id')
                            ->where('is_standard', true);
                    });
            });
        } else {
            $query->whereNull('stripe_account_id')
                ->where('is_standard', true);
        }

        return $query->where('active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('code', 'asc');
    }

    /**
     * Build the article group code Select field.
     */
    private static function buildArticleGroupCodeSelect(): Select
    {
        return Select::make('article_group_code')
            ->label('Article Group Code (SAF-T)')
            ->options(function (Get $get, $record) {
                $stripeAccountId = self::resolveStripeAccountId($get, $record);

                return self::buildArticleGroupCodeQuery($stripeAccountId)
                    ->get()
                    ->mapWithKeys(fn ($record) => [$record->code => $record->code.' - '.$record->name]);
            })
            ->getSearchResultsUsing(function (string $search, Get $get, $record) {
                $stripeAccountId = self::resolveStripeAccountId($get, $record);

                return self::buildArticleGroupCodeQuery($stripeAccountId)
                    ->where(function ($q) use ($search) {
                        $q->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn ($record) => [$record->code => $record->code.' - '.$record->name]);
            })
            ->getOptionLabelUsing(function ($value) {
                $code = ArticleGroupCode::where('code', $value)->first();

                return $code ? $code->code.' - '.$code->name : $value;
            })
            ->searchable()
            ->preload()
            ->default(fn ($record) => $record?->article_group_code ?? '04999')
            ->helperText('PredefinedBasicID-04: Product category for SAF-T reporting. VAT rate will be set from the selected code.')
            ->placeholder('Select article group')
            ->live(onBlur: false)
            ->afterStateUpdated(function ($state, $set) {
                if ($state) {
                    $articleGroupCode = ArticleGroupCode::where('code', $state)->first();
                    if ($articleGroupCode && $articleGroupCode->default_vat_percent !== null) {
                        $set('vat_percent', $articleGroupCode->default_vat_percent * 100);
                    } else {
                        $set('vat_percent', null);
                    }
                } else {
                    $set('vat_percent', null);
                }
            });
    }

    /**
     * Build the VAT percent TextInput field for create forms.
     */
    private static function buildVatPercentInputForCreate(): TextInput
    {
        return TextInput::make('vat_percent')
            ->label('VAT Percentage (%)')
            ->numeric()
            ->step(0.01)
            ->minValue(0)
            ->maxValue(100)
            ->suffix('%')
            ->default(25)
            ->helperText('VAT percentage for this product. Auto-set from article group code (default 04999 = 25%), but can be manually overridden.')
            ->placeholder('Auto from article group code')
            ->reactive();
    }

    /**
     * Build the VAT percent TextInput field for edit forms.
     */
    private static function buildVatPercentInputForEdit(): TextInput
    {
        return TextInput::make('vat_percent')
            ->label('VAT Percentage (%)')
            ->numeric()
            ->step(0.01)
            ->minValue(0)
            ->maxValue(100)
            ->suffix('%')
            ->formatStateUsing(function ($state, $record) {
                if ($state !== null && $state !== '') {
                    return $state;
                }
                $code = $record?->article_group_code;
                if (! $code) {
                    return null;
                }
                $agc = ArticleGroupCode::where('code', $code)->where('active', true)->first();
                if ($agc && $agc->default_vat_percent !== null) {
                    return (float) $agc->default_vat_percent * 100;
                }

                return null;
            })
            ->dehydrateStateUsing(function ($state, $record) {
                if ($state !== null && $state !== '') {
                    return $state;
                }
                $code = $record?->article_group_code;
                if (! $code) {
                    return null;
                }
                $agc = ArticleGroupCode::where('code', $code)->where('active', true)->first();
                if ($agc && $agc->default_vat_percent !== null) {
                    return (float) $agc->default_vat_percent * 100;
                }

                return null;
            })
            ->helperText('VAT percentage for this product. Auto-set from article group code, but can be manually overridden.')
            ->placeholder('Auto from article group code')
            ->reactive();
    }
}
