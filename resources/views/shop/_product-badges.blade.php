{{--
    Partial: _product-badges
    Variables: $product, $stockAvailable, $showNewBadge, $favoriteProductIds, $showFavBadge
--}}
@php
    $isNew = $showNewBadge && $product->created_at && $product->created_at->gt(now()->subDays(30));
    $isStamm = $showFavBadge && isset($favoriteProductIds[$product->id]);
@endphp

{{-- Unavailable overlay badge --}}
@if(!$stockAvailable)
    <div class="absolute top-2 left-2 z-10 bg-red-100 text-red-600 text-xs font-medium px-2 py-0.5 rounded-full">
        Nicht verfügbar
    </div>
@endif

{{-- New badge --}}
@if($isNew && $stockAvailable)
    <div class="absolute top-2 left-2 z-10 bg-amber-400 text-white text-xs font-bold px-2 py-0.5 rounded-full">
        Neu
    </div>
@endif

{{-- Stammsortiment heart badge (top right) --}}
@auth
@if($isStamm)
    <div class="absolute top-2 right-2 z-10 text-amber-500 text-base leading-none" title="Im Stammsortiment">♥</div>
@endif
@endauth
