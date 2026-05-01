{{--
    Partial: _product-fav
    Variables: $product, $favoriteProductIds
    Only renders for authenticated users.
--}}
@auth
@php $inFav = isset($favoriteProductIds[$product->id]); @endphp
<form method="POST" action="{{ route('account.favorites.store') }}" class="mt-1">
    @csrf
    <input type="hidden" name="product_id" value="{{ $product->id }}">
    <button type="submit"
            title="{{ $inFav ? 'Aus Stammsortiment entfernen' : 'Zum Stammsortiment hinzufügen' }}"
            class="text-xs flex items-center gap-1 transition-colors
                   {{ $inFav ? 'text-amber-500 hover:text-amber-600' : 'text-gray-300 hover:text-amber-400' }}">
        {{ $inFav ? '♥' : '♡' }}
        <span class="sr-only">{{ $inFav ? 'Im Stammsortiment' : 'Zum Stammsortiment' }}</span>
    </button>
</form>
@endauth
