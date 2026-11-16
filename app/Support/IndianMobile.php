<?php

namespace App\Support;

/**
 * Indian mobile number normalisation.
 *
 * Accepts common input forms — "98765 43210", "+91 98765-43210",
 * "09876543210", "919876543210" — and stores the 10-digit national number.
 */
final class IndianMobile
{
    /**
     * The 10-digit national number, or null if the input is not a valid Indian mobile.
     */
    public static function normalise(?string $input): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $input);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }

    /**
     * Display format: "+91 98765 43210".
     */
    public static function format(?string $number): ?string
    {
        if ($number === null || ! preg_match('/^\d{10}$/', $number)) {
            return $number;
        }

        return '+91 '.substr($number, 0, 5).' '.substr($number, 5);
    }
}
