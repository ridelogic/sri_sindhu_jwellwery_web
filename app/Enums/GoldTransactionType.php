<?php

namespace App\Enums;

enum GoldTransactionType: string
{
    case Credit = 'credit';

    public function label(): string
    {
        return 'Gold credited';
    }
}
