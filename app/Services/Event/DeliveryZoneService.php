<?php
declare(strict_types=1);
namespace App\Services\Event;

/**
 * Liefergebiet und Entfernungsaufschlag.
 *
 * Lager-Referenzpunkt: Industriestr.13, 64280 Roßdorf (Standard)
 * Liefergebiet: 8 km um das Lager
 *
 * Außerhalb 8 km:
 *   - Entfernungsaufschlag über Artikel 59200 "Entfernungsaufschlag (pro km)"
 *   - 0,50 € pro Zusatz-km (einfacher Weg)
 *   - Zusatz-km = Distanz - 8
 */
class DeliveryZoneService
{
    // Artikel-Nummer für Entfernungsaufschlag
    public const ARTICLE_DISTANCE_SURCHARGE = '59200';

    // Preis pro km in Milli-Cent: 0,50 € = 500_000
    public const PRICE_PER_KM_MILLI = 500_000;

    // Liefergebiet-Radius in km
    public const DELIVERY_RADIUS_KM = 8;

    // Standard-Lager Roßdorf
    private float $warehouseLat = 49.8597;
    private float $warehouseLng = 8.7494;
    private string $warehouseAddress = 'Industriestr. 13, 64280 Roßdorf';

    public function setWarehouse(float $lat, float $lng): self
    {
        $this->warehouseLat = $lat;
        $this->warehouseLng = $lng;
        return $this;
    }

    /**
     * Berechnet Distanz in km (Luftlinie via Haversine).
     */
    public function distanceKm(float $toLat, float $toLng): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($toLat - $this->warehouseLat);
        $dLng = deg2rad($toLng - $this->warehouseLng);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->warehouseLat)) * cos(deg2rad($toLat)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * asin(sqrt($a));
    }

    /**
     * Ist der Eventort innerhalb des Liefergebiets?
     */
    public function isInDeliveryZone(float $lat, float $lng): bool
    {
        return $this->distanceKm($lat, $lng) <= self::DELIVERY_RADIUS_KM;
    }

    /**
     * Berechnet den Entfernungsaufschlag in Milli-Cent.
     * Gibt 0 zurück wenn im Liefergebiet.
     * Gibt Zusatz-km Anzahl zurück als zweiten Wert.
     *
     * @return array{surcharge_milli: int, extra_km: int}
     */
    public function calculateSurcharge(float $lat, float $lng): array
    {
        $distance = $this->distanceKm($lat, $lng);
        $extraKm  = max(0, (int) ceil($distance) - self::DELIVERY_RADIUS_KM);

        return [
            'surcharge_milli' => $extraKm * self::PRICE_PER_KM_MILLI,
            'extra_km'        => $extraKm,
            'total_km'        => (int) ceil($distance),
        ];
    }

    public function getWarehouseAddress(): string
    {
        return $this->warehouseAddress;
    }
}
