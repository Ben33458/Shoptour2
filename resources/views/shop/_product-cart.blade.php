{{--
    Partial: _product-cart
    Variables: $product, $stockAvailable
--}}
@php
    $isDisabled = !$stockAvailable
        || $product->availability_mode === \App\Models\Catalog\Product::AVAILABILITY_OUT_OF_STOCK
        || $product->availability_mode === \App\Models\Catalog\Product::AVAILABILITY_DISCONTINUED;
@endphp
<form method="POST" action="{{ route('cart.add') }}" class="flex gap-1 mt-2">
    @csrf
    <input type="hidden" name="product_id" value="{{ $product->id }}">
    <input type="number" name="qty" value="1" min="1" max="999"
           @disabled($isDisabled)
           class="w-14 border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:bg-gray-100 disabled:cursor-not-allowed">
    <button type="submit" @disabled($isDisabled)
            class="flex-1 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed text-white text-xs font-medium rounded-lg px-3 py-1.5 transition-colors whitespace-nowrap">
        In den Warenkorb
    </button>
</form>
