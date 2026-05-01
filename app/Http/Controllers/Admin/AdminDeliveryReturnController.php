<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental\DeliveryReturn;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDeliveryReturnController extends Controller
{
    public function index(Request $request): View
    {
        $query = DeliveryReturn::with(['customer', 'driver', 'items.article'])
            ->orderBy('returned_at', 'desc');
        if ($request->filled('return_type')) {
            $query->where('return_type', $request->return_type);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        $deliveryReturns = $query->paginate(30);
        return view('admin.rental.delivery-returns.index', compact('deliveryReturns'));
    }

    public function show(DeliveryReturn $deliveryReturn): View
    {
        $deliveryReturn->load(['customer', 'driver', 'order', 'items.article', 'items.generatedFeeArticle']);
        return view('admin.rental.delivery-returns.show', compact('deliveryReturn'));
    }
}
