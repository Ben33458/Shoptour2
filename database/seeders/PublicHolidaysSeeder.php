<?php
namespace Database\Seeders;

use App\Models\Employee\PublicHoliday;
use Illuminate\Database\Seeder;

class PublicHolidaysSeeder extends Seeder
{
    public function run(): void
    {
        // Half-holidays: Dec 24 (Heiligabend) and Dec 31 (Silvester) — each counts as 0.5 Urlaubstag
        $halfDays = [
            '2024-12-24', '2024-12-31',
            '2025-12-24', '2025-12-31',
            '2026-12-24', '2026-12-31',
            '2027-12-24', '2027-12-31',
        ];
        $halfDayEntries = [
            ['date' => '2024-12-24', 'name' => 'Heiligabend (halber Tag)'],
            ['date' => '2024-12-31', 'name' => 'Silvester (halber Tag)'],
            ['date' => '2025-12-24', 'name' => 'Heiligabend (halber Tag)'],
            ['date' => '2025-12-31', 'name' => 'Silvester (halber Tag)'],
            ['date' => '2026-12-24', 'name' => 'Heiligabend (halber Tag)'],
            ['date' => '2026-12-31', 'name' => 'Silvester (halber Tag)'],
            ['date' => '2027-12-24', 'name' => 'Heiligabend (halber Tag)'],
            ['date' => '2027-12-31', 'name' => 'Silvester (halber Tag)'],
        ];
        foreach ($halfDayEntries as $h) {
            PublicHoliday::firstOrCreate(['date' => $h['date']], ['name' => $h['name'], 'state' => 'HE', 'is_half_day' => true]);
        }

        $holidays = [
            // 2024
            ['date' => '2024-01-01', 'name' => 'Neujahr'],
            ['date' => '2024-03-29', 'name' => 'Karfreitag'],
            ['date' => '2024-04-01', 'name' => 'Ostermontag'],
            ['date' => '2024-05-01', 'name' => 'Tag der Arbeit'],
            ['date' => '2024-05-09', 'name' => 'Christi Himmelfahrt'],
            ['date' => '2024-05-20', 'name' => 'Pfingstmontag'],
            ['date' => '2024-05-30', 'name' => 'Fronleichnam'],
            ['date' => '2024-10-03', 'name' => 'Tag der Deutschen Einheit'],
            ['date' => '2024-12-25', 'name' => '1. Weihnachtstag'],
            ['date' => '2024-12-26', 'name' => '2. Weihnachtstag'],
            // 2025
            ['date' => '2025-01-01', 'name' => 'Neujahr'],
            ['date' => '2025-04-18', 'name' => 'Karfreitag'],
            ['date' => '2025-04-21', 'name' => 'Ostermontag'],
            ['date' => '2025-05-01', 'name' => 'Tag der Arbeit'],
            ['date' => '2025-05-29', 'name' => 'Christi Himmelfahrt'],
            ['date' => '2025-06-09', 'name' => 'Pfingstmontag'],
            ['date' => '2025-06-19', 'name' => 'Fronleichnam'],
            ['date' => '2025-10-03', 'name' => 'Tag der Deutschen Einheit'],
            ['date' => '2025-12-25', 'name' => '1. Weihnachtstag'],
            ['date' => '2025-12-26', 'name' => '2. Weihnachtstag'],
            // 2026
            ['date' => '2026-01-01', 'name' => 'Neujahr'],
            ['date' => '2026-04-03', 'name' => 'Karfreitag'],
            ['date' => '2026-04-06', 'name' => 'Ostermontag'],
            ['date' => '2026-05-01', 'name' => 'Tag der Arbeit'],
            ['date' => '2026-05-14', 'name' => 'Christi Himmelfahrt'],
            ['date' => '2026-05-25', 'name' => 'Pfingstmontag'],
            ['date' => '2026-06-04', 'name' => 'Fronleichnam'],
            ['date' => '2026-10-03', 'name' => 'Tag der Deutschen Einheit'],
            ['date' => '2026-12-25', 'name' => '1. Weihnachtstag'],
            ['date' => '2026-12-26', 'name' => '2. Weihnachtstag'],
            // 2027
            ['date' => '2027-01-01', 'name' => 'Neujahr'],
            ['date' => '2027-03-26', 'name' => 'Karfreitag'],
            ['date' => '2027-03-29', 'name' => 'Ostermontag'],
            ['date' => '2027-05-01', 'name' => 'Tag der Arbeit'],
            ['date' => '2027-05-06', 'name' => 'Christi Himmelfahrt'],
            ['date' => '2027-05-17', 'name' => 'Pfingstmontag'],
            ['date' => '2027-05-27', 'name' => 'Fronleichnam'],
            ['date' => '2027-10-03', 'name' => 'Tag der Deutschen Einheit'],
            ['date' => '2027-12-25', 'name' => '1. Weihnachtstag'],
            ['date' => '2027-12-26', 'name' => '2. Weihnachtstag'],
        ];

        foreach ($holidays as $h) {
            PublicHoliday::firstOrCreate(['date' => $h['date']], ['name' => $h['name'], 'state' => 'HE']);
        }
    }
}
