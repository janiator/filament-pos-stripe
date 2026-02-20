<?php

namespace App\Filament\Resources\Addons\Schemas;

use App\Enums\AddonType;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class AddonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Type')
                    ->options(collect(AddonType::cases())->mapWithKeys(fn (AddonType $t) => [$t->value => $t->label()])->all())
                    ->required()
                    ->native(false)
                    ->rules([
                        'required',
                        Rule::enum(AddonType::class),
                    ])
                    ->unique(
                        table: 'addons',
                        column: 'type',
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('store_id', \Filament\Facades\Filament::getTenant()?->id)
                    ),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Hidden::make('store_id')
                    ->default(fn () => \Filament\Facades\Filament::getTenant()?->id),
            ]);
    }
}
