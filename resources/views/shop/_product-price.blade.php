{{--
    Partial: _product-price
    Variables:
      $price            – object with grossMilli/netMilli, or null
      $pfand            – int milli
      $priceDisplayMode – 'brutto'|'netto'
      $isBusiness       – bool
      $showPfand        – bool
      $grundpreisText   – string|null  (optional, PAngV Grundpreis, e.g. "1,06 €/L")
--}}
<div class="mt-2">
    @if($price)
        @php
            $displayMilli = $priceDisplayMode === 'netto' ? $price->netMilli : $price->grossMilli;
        @endphp
        <p class="text-sm font-bold text-gray-900">{{ milli_to_eur($displayMilli) }}</p>
        @if($isBusiness && $priceDisplayMode !== 'netto')
            <p class="text-xs text-gray-400">{{ milli_to_eur($price->netMilli) }} zzgl. MwSt.</p>
        @endif
        @if($showPfand && $pfand > 0)
            <p class="text-xs text-amber-600 mt-0.5">+ {{ milli_to_eur($pfand) }} Pfand</p>
        @endif
        @if(!empty($grundpreisText))
            <p class="text-xs text-gray-400 mt-0.5">{{ $grundpreisText }}</p>
        @endif
    @else
        <p class="text-xs text-gray-400 italic mt-1">Auf Anfrage</p>
    @endif
</div>
