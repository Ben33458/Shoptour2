<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Catalog\Gebinde;
use App\Models\User;
use App\Services\Orders\PfandCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Session-basierter Leergut-Warenkorb.
 *
 * Speichert welche Leergut-Positionen der Kunde zurückgeben möchte.
 * Session-Struktur: leergut_cart → [ leergut_art_nr => qty, ... ]
 *
 * Art.-Nr.-Muster: Pfand 5,10 € = 510 Cent → Leergut-Art.-Nr. "1510" (führende "1" + Cent)
 */
class LeergutCartService
{
    private const SESSION_KEY = 'leergut_cart';

    public function __construct(
        private readonly Request          $request,
        private readonly CartService      $cartService,
        private readonly PfandCalculator  $pfandCalculator,
    ) {}

    /**
     * Alle Leergut-Typen die zu aktiven Produkten passen, automatisch via Pfand-Muster ermittelt.
     *
     * Logik: Produkt → Gebinde → PfandSet → Pfand-Total in Milli-Cent
     *        → Cent-Betrag → Leergut-Art.-Nr. "1" + Cent  (z.B. 342 ct → "1342")
     *        → Lookup in wawi_artikel
     *
     * @return Collection<int, object{leergut_art_nr: string, leergut_name: string, unit_price_gross_milli: int}>
     */
    public function getAllTypes(): Collection
    {
        // Fetch distinct (pfand_set_id, leergut_art_nr) pairs from active products.
        // leergut_art_nr on gebinde overrides the auto-computed "1"+cents art-nr,
        // needed for Faß deposits whose wawi articles don't follow that naming convention.
        $pfandSetRows = DB::table('products')
            ->join('gebinde', 'gebinde.id', '=', 'products.gebinde_id')
            ->where('products.active', true)
            ->whereNotNull('gebinde.pfand_set_id')
            ->select('gebinde.pfand_set_id', DB::raw('MAX(gebinde.leergut_art_nr) as leergut_art_nr'))
            ->groupBy('gebinde.pfand_set_id')
            ->get();

        if ($pfandSetRows->isEmpty()) {
            return collect();
        }

        $artNrs = [];
        foreach ($pfandSetRows as $row) {
            if ($row->leergut_art_nr !== null) {
                $artNrs[] = $row->leergut_art_nr;
                continue;
            }
            $gebinde = new Gebinde(['pfand_set_id' => (int) $row->pfand_set_id]);
            $milli   = $this->pfandCalculator->totalForGebinde($gebinde);
            if ($milli <= 0) {
                continue;
            }
            $cents = (int) round($milli / 10_000);
            if ($cents <= 0) {
                continue;
            }
            $artNrs[] = '1' . $cents;
        }

        if (empty($artNrs)) {
            return collect();
        }

        return DB::table('wawi_artikel')
            ->whereIn('cArtNr', array_unique($artNrs))
            ->where('cName', 'LIKE', 'Leergut%')
            ->where('cName', 'NOT LIKE', 'Leergut 6er%')
            ->orderBy('cName')
            ->get(['cArtNr', 'cName', 'fVKNetto'])
            ->map(function ($row) {
                // Faß leergut articles store the brutto deposit as the netto price (VAT-exempt).
                $netto = abs((float) $row->fVKNetto);
                $grossMilli = str_starts_with($row->cName, 'Leergut Faß')
                    ? (int) round($netto * 1_000_000)
                    : (int) round($netto * 1.19 * 1_000_000);

                return (object) [
                    'leergut_art_nr'         => $row->cArtNr,
                    'leergut_name'           => $row->cName,
                    'unit_price_gross_milli' => $grossMilli,
                ];
            });
    }

    /**
     * Alle Leergut-Positionen mit qty > 0, keyed by leergut_art_nr.
     * Liest Preise direkt aus wawi_artikel (unabhängig von product_leergut).
     *
     * @return array<string, array{leergut: object, qty: int, line_credit_milli: int}>
     */
    public function getItems(): array
    {
        $cart = $this->load();
        if (empty($cart)) {
            return [];
        }

        $wawiRecords = DB::table('wawi_artikel')
            ->whereIn('cArtNr', array_keys($cart))
            ->get(['cArtNr', 'cName', 'fVKNetto'])
            ->keyBy('cArtNr');

        $items = [];
        foreach ($cart as $artNr => $qty) {
            $row = $wawiRecords->get($artNr);
            if ($row === null || $qty <= 0) {
                continue;
            }
            $netto = abs((float) $row->fVKNetto);
            $grossMilli = str_starts_with($row->cName, 'Leergut Faß')
                ? (int) round($netto * 1_000_000)
                : (int) round($netto * 1.19 * 1_000_000);
            $items[(string) $artNr] = [
                'leergut'           => (object) [
                    'leergut_art_nr'         => $row->cArtNr,
                    'leergut_name'           => $row->cName,
                    'unit_price_gross_milli' => $grossMilli,
                ],
                'qty'               => $qty,
                'line_credit_milli' => $grossMilli * $qty,
            ];
        }

        return $items;
    }

    /**
     * Gesamte Leergut-Gutschrift in Milli-Cent (immer positiv).
     */
    public function getCreditTotal(): int
    {
        $total = 0;
        foreach ($this->getItems() as $item) {
            $total += $item['line_credit_milli'];
        }
        return $total;
    }

    /**
     * Leergut-Ausgleich: füllt den Leergut-Cart automatisch basierend auf dem
     * Pfand-Betrag jedes Produkts im Getränke-Warenkorb.
     *
     * Muster: Pfand 5,10 € → 510 Cent → Leergut-Art.-Nr. "1510"
     */
    public function fillFromCart(?User $user): void
    {
        $cartData = $this->cartService->calculate($user);
        if (empty($cartData['items'])) {
            return;
        }

        $cart = [];

        foreach ($cartData['items'] as $item) {
            $pfandPerUnit = $item['pfand_per_unit'] ?? 0;
            if ($pfandPerUnit <= 0) {
                continue;
            }

            // Faß gebinde override: use wawi art-nr directly instead of auto-computing
            $leergutArtNr = $item['product']?->gebinde?->leergut_art_nr ?? null;
            if ($leergutArtNr !== null) {
                $cart[$leergutArtNr] = ($cart[$leergutArtNr] ?? 0) + (int) $item['qty'];
                continue;
            }

            // milli-cent → cent: 5_100_000 / 10_000 = 510
            $pfandCents = (int) round($pfandPerUnit / 10_000);
            if ($pfandCents <= 0) {
                continue;
            }
            $cart['1' . $pfandCents] = ($cart['1' . $pfandCents] ?? 0) + (int) $item['qty'];
        }

        $this->save($cart);
    }

    /**
     * Menge für einen Leergut-Typ setzen. Bei qty = 0 wird die Position entfernt.
     */
    public function updateQty(string $artNr, int $qty): void
    {
        $cart = $this->load();

        if ($qty <= 0) {
            unset($cart[$artNr]);
        } else {
            $cart[$artNr] = $qty;
        }

        $this->save($cart);
    }

    /**
     * Leergut-Cart leeren.
     */
    public function clear(): void
    {
        $this->request->session()->forget(self::SESSION_KEY);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /** @return array<string, int> */
    private function load(): array
    {
        return $this->request->session()->get(self::SESSION_KEY, []);
    }

    /** @param array<string, int> $cart */
    private function save(array $cart): void
    {
        $this->request->session()->put(self::SESSION_KEY, $cart);
    }
}
