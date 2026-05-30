<?php

declare(strict_types=1);

namespace App\Constants;

class EventLabels
{
    public static function eventTypes(): array
    {
        return [
            'kerb'               => 'Kerb',
            'fastnacht'          => 'Fastnacht',
            'sommerfest'         => 'Sommerfest',
            'geburtstag'         => 'Geburtstag',
            'hochzeit'           => 'Hochzeit',
            'kommunion'          => 'Kommunion',
            'konfirmation'       => 'Konfirmation',
            'firmenfeier'        => 'Firmenfeier',
            'vereinsfest'        => 'Vereinsfest',
            'weihnachtsmarkt'    => 'Weihnachtsmarkt',
            'richtfest'          => 'Richtfest',
            'sportveranstaltung' => 'Sportveranstaltung',
            'schulfest'          => 'Schulfest',
            'jubilaeum'          => 'Jubiläum',
            'sonstiges'          => 'Sonstiges',
            'unknown'            => 'Unbekannt',
        ];
    }

    public static function requestChannels(): array
    {
        return [
            'phone'      => 'Telefon',
            'email'      => 'E-Mail',
            'whatsapp'   => 'WhatsApp',
            'in_person'  => 'Persönlich',
            'shop'       => 'Shop',
            'ninox'      => 'Ninox',
            'jtl'        => 'JTL',
            'other'      => 'Sonstiges',
            'unknown'    => 'Unbekannt',
        ];
    }

    public static function itemTypes(): array
    {
        return [
            'beverage'   => 'Getränke',
            'rental'     => 'Verleih',
            'delivery'   => 'Lieferung',
            'pickup'     => 'Abholung',
            'deposit'    => 'Pfand',
            'discount'   => 'Rabatt',
            'fee'        => 'Gebühr',
            'free_text'  => 'Freitext',
            'other'      => 'Sonstiges',
        ];
    }

    public static function logisticsTypes(): array
    {
        return [
            'delivery'            => 'Lieferung',
            'pickup'              => 'Abholung',
            'empty_goods_pickup'  => 'Leerrücknahme',
            'additional_delivery' => 'Nachlieferung',
            'setup'               => 'Aufbau',
            'dismantling'         => 'Abbau',
            'inspection'          => 'Überprüfung',
            'other'               => 'Sonstiges',
        ];
    }

    public static function logisticsStatuses(): array
    {
        return [
            'planned'     => 'Geplant',
            'scheduled'   => 'Eingeplant',
            'in_progress' => 'In Bearbeitung',
            'done'        => 'Erledigt',
            'cancelled'   => 'Storniert',
            'failed'      => 'Fehlgeschlagen',
        ];
    }

    public static function timeFlexibility(): array
    {
        return [
            'fixed'          => 'Fester Termin',
            'flexible'       => 'Flexibel',
            'morning'        => 'Vormittags',
            'afternoon'      => 'Nachmittags',
            'by_arrangement' => 'Nach Absprache',
            'unknown'        => 'Unbekannt',
        ];
    }

    public static function reservationTypes(): array
    {
        return [
            'soft' => 'Vorläufig',
            'hard' => 'Fest',
        ];
    }

    public static function reservationStatuses(): array
    {
        return [
            'requested' => 'Angefragt',
            'reserved'  => 'Reserviert',
            'confirmed' => 'Bestätigt',
            'released'  => 'Freigegeben',
            'cancelled' => 'Storniert',
            'conflict'  => 'Konflikt',
        ];
    }

    public static function taskStatuses(): array
    {
        return [
            'open'        => 'Offen',
            'in_progress' => 'In Bearbeitung',
            'done'        => 'Erledigt',
            'cancelled'   => 'Abgebrochen',
        ];
    }

    public static function taskTypes(): array
    {
        return [
            'clarification'     => 'Klärung',
            'offer'             => 'Angebot',
            'delivery'          => 'Lieferung',
            'pickup'            => 'Abholung',
            'rental_check'      => 'Inventarprüfung',
            'payment'           => 'Zahlung',
            'post_calculation'  => 'Nachkalkulation',
            'customer_followup' => 'Kundennachverfolgung',
            'other'             => 'Sonstiges',
        ];
    }

    public static function label(array $map, ?string $value): string
    {
        return $map[$value ?? ''] ?? ($value ?: '—');
    }
}
