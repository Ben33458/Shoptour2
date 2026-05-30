<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Payments\IbanValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankLookupController extends Controller
{
    public function __invoke(Request $request, IbanValidator $validator): JsonResponse
    {
        $raw  = (string) $request->query('iban', '');
        $iban = $validator->normalize($raw);

        // Only handle German IBANs with enough length to extract BLZ
        if (! str_starts_with($iban, 'DE') || strlen($iban) < 12) {
            return response()->json(['bank_name' => null, 'bic' => null]);
        }

        $blz = substr($iban, 4, 8);

        $row = DB::table('bank_codes')->where('blz', $blz)->first(['bank_name', 'bic']);

        return response()->json([
            'bank_name' => $row?->bank_name,
            'bic'       => $row?->bic,
        ]);
    }
}
