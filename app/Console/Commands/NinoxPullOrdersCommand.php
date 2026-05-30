<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ninox\NinoxPullService;
use Illuminate\Console\Command;

class NinoxPullOrdersCommand extends Command
{
    protected $signature   = 'ninox:pull-orders';
    protected $description = 'Importiert Ninox-Bestellannahme-Einträge ohne Tour nach shoptour2.';

    public function handle(NinoxPullService $service): int
    {
        $result = $service->pullStandaloneOrders();
        $this->info("Importiert: {$result['imported']}  Fehlgeschlagen: {$result['failed']}");

        return self::SUCCESS;
    }
}
