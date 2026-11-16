<?php

namespace App\Enums;

enum GoldPurity: string
{
    case K22 = '22K';
    case K24 = '24K';

    public function label(): string
    {
        return $this->value.' Gold';
    }

    public function description(): string
    {
        return match ($this) {
            self::K22 => '91.6% pure gold — the standard for jewellery',
            self::K24 => '99.9% pure gold — coins and bars',
        };
    }
}
