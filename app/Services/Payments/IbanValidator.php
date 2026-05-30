<?php

declare(strict_types=1);

namespace App\Services\Payments;

class IbanValidator
{
    public function normalize(string $raw): string
    {
        return strtoupper(preg_replace('/\s+/', '', $raw));
    }

    public function isValid(string $iban): bool
    {
        $iban = $this->normalize($iban);

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }

        // Mod-97: move first 4 chars to end, replace letters with digits (A=10…Z=35)
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric    = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        return bcmod($numeric, '97') === '1';
    }

    /** Returns e.g. "DE89 **** **** **** **** 3000" */
    public function mask(string $iban): string
    {
        $iban = $this->normalize($iban);
        $len  = strlen($iban);

        return substr($iban, 0, 4)
            . str_repeat('*', max(0, $len - 8))
            . substr($iban, -4);
    }
}
