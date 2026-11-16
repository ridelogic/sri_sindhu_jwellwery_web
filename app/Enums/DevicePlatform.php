<?php

namespace App\Enums;

enum DevicePlatform: string
{
    case Android = 'android';
    case Ios = 'ios';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Android => 'Android',
            self::Ios => 'iPhone / iPad',
            self::Web => 'Web browser',
        };
    }
}
