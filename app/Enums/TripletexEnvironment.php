<?php

namespace App\Enums;

enum TripletexEnvironment: string
{
    case Test = 'test';
    case Prod = 'prod';

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Test (api.tripletex.io)',
            self::Prod => 'Production (tripletex.no)',
        };
    }
}
