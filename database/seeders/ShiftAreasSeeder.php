<?php
namespace Database\Seeders;

use App\Models\Employee\ShiftArea;
use Illuminate\Database\Seeder;

class ShiftAreasSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['name' => 'Kasse',    'color' => '#f472b6'],
            ['name' => 'Lager',    'color' => '#fb923c'],
            ['name' => 'Verkauf',  'color' => '#4ade80'],
            ['name' => 'Büro',     'color' => '#38bdf8'],
            ['name' => 'Reinigung','color' => '#c084fc'],
        ];
        foreach ($areas as $a) {
            ShiftArea::firstOrCreate(['name' => $a['name']], $a);
        }
    }
}
