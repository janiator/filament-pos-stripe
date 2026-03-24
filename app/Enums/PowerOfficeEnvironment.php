<?php

namespace App\Enums;

enum PowerOfficeEnvironment: string
{
    case Dev = 'dev';
    case Prod = 'prod';

    public function label(): string
    {
        return match ($this) {
            self::Dev => 'Demo / test',
            self::Prod => 'Production',
        };
    }
}
