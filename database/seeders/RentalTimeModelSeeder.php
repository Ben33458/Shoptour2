<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Rental\RentalTimeModel;
use Illuminate\Database\Seeder;

class RentalTimeModelSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'name'               => 'Wochenende',
                'rule_type'          => 'weekend',
                'min_duration_hours' => 24,
                'default_for_events' => false,
                'metadata'           => ['description' => 'Freitag bis Montag, 3 Nächte'],
            ],
            [
                'name'               => 'Woche',
                'rule_type'          => 'week',
                'min_duration_hours' => 120,
                'default_for_events' => false,
                'metadata'           => ['description' => 'Montag bis Freitag, 5 Tage'],
            ],
            [
                'name'               => 'Werktage',
                'rule_type'          => 'workdays',
                'min_duration_hours' => 24,
                'default_for_events' => false,
                'metadata'           => ['description' => 'Mo–Fr, min. 1 Tag'],
            ],
            [
                'name'               => 'Verlängerung',
                'rule_type'          => 'extension',
                'min_duration_hours' => 24,
                'default_for_events' => false,
                'metadata'           => ['description' => 'Verlängerung des bestehenden Buchungszeitraums um 1 Tag'],
            ],
            [
                'name'               => 'Veranstaltung',
                'rule_type'          => 'event',
                'min_duration_hours' => 2,
                'default_for_events' => true,
                'metadata'           => ['description' => 'Standard-Zeitmodell für Veranstaltungsbestellungen'],
            ],
        ];

        foreach ($models as $data) {
            RentalTimeModel::firstOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
