<?php

namespace App\Support;

class Money
{
    /**
     * Convert a decimal amount ("5000", "5000.5", "5000.50") to integer paise
     * WITHOUT floating point, so financial maths is exact.
     */
    public static function toPaise(int|float|string $amount): int
    {
        $value = trim(is_float($amount) ? number_format($amount, 2, '.', '') : (string) $amount);

        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) {
            throw new \InvalidArgumentException('Invalid money amount.');
        }

        $paise = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$paise : $paise;
    }

    /**
     * Integer paise back to a 2-decimal string for decimal columns ("55000.00").
     */
    public static function fromPaise(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $paise = abs($paise);

        return $sign.intdiv($paise, 100).'.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Exact amount × whole number (e.g. monthly contribution × months).
     */
    public static function multiply(int|float|string $amount, int $times): string
    {
        return self::fromPaise(self::toPaise($amount) * $times);
    }

    /**
     * Format an amount in Indian Rupees using Indian digit grouping (e.g. ₹1,00,000).
     * Whole-rupee amounts have no decimals; amounts with paise show two (₹7,000.50).
     */
    public static function inr(int|float|string $amount, bool $withSymbol = true): string
    {
        $amount = (float) $amount;
        $paise = (int) round(abs($amount) * 100);
        $whole = (string) intdiv($paise, 100);
        $fraction = $paise % 100;

        if (strlen($whole) > 3) {
            $lastThree = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $whole = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$lastThree;
        }

        $formatted = $whole.($fraction > 0 ? '.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT) : '');

        return ($amount < 0 ? '-' : '').($withSymbol ? '₹' : '').$formatted;
    }
}
