<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripeProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $rawPayload = $request->getContent();
        $signature  = (string) $request->header('Stripe-Signature', '');

        $provider = app(StripeProvider::class);
        $provider->handleWebhook($rawPayload, $signature);

        return response('', 200);
    }
}
