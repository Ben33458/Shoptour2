<?php

declare(strict_types=1);

/**
 * Global helper functions for monetary display.
 *
 * All monetary values in the database are stored as milli-cents
 * (integer × 1/1,000,000 EUR).  These helpers format them for display
 * in German locale (comma as decimal separator, dot as thousand separator).
 */

if (! function_exists('milli_to_eur')) {
    /**
     * Convert a milli-cent value to a display string in German EUR format.
     *
     * Examples:
     *   milli_to_eur(1_000_000)   → "1,00 €"
     *   milli_to_eur(500_000)     → "0,50 €"
     *   milli_to_eur(-2_500_000)  → "-2,50 €"
     */
    function milli_to_eur(int $milli): string
    {
        return number_format($milli / 1_000_000, 2, ',', '.') . ' €';
    }
}

if (! function_exists('eur_to_milli')) {
    /**
     * Convert a decimal EUR value (from user input) to milli-cents for storage.
     *
     * Examples:
     *   eur_to_milli(1.00)   → 1_000_000
     *   eur_to_milli(0.50)   → 500_000
     *   eur_to_milli(-2.50)  → -2_500_000
     */
    function eur_to_milli(float $eur): int
    {
        return (int) round($eur * 1_000_000);
    }
}
