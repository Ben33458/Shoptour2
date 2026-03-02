<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Admin\Invoice;
use App\Models\Pricing\AppSetting;
use App\Services\Payments\StripeProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice): RedirectResponse
    {
        if (AppSetting::get('stripe.enabled', '0') !== '1') {
            abort(503, 'Online-Zahlung ist derzeit nicht verfügbar.');
        }

        if (! $invoice->isFinalized()) {
            abort(422, 'Rechnung ist noch nicht finalisiert.');
        }

        $provider = app(StripeProvider::class);

        $successUrl = route('admin.invoices.index') . '?paid=1';
        $cancelUrl  = route('admin.orders.invoice', ['order' => $invoice->order_id]);

        $url = $provider->createCheckoutSession($invoice, $successUrl, $cancelUrl);

        return redirect($url);
    }
}
