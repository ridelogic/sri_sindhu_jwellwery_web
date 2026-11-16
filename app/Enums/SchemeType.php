<?php

namespace App\Enums;

enum SchemeType: string
{
    case Saving = 'saving';
    case Exchange = 'exchange';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<string, string> value => label, for selects */
    public static function options(): array
    {
        return array_column(array_map(fn (self $c) => ['v' => $c->value, 'l' => $c->label()], self::cases()), 'l', 'v');
    }
}
