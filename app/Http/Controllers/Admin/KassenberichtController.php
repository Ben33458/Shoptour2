<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Admin\KassenberichtService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class KassenberichtController extends Controller
{
    public function __construct(
        private readonly KassenberichtService $service,
    ) {}

    public function index(Request $request): View
    {
        $monat  = $request->input('month', now()->format('Y-m'));
        $monat  = $this->clampMonat($monat);
        $bericht = $this->service->forMonth($monat);

        return view('admin.kassenbericht.index', compact('bericht', 'monat'));
    }

    public function pdf(Request $request): Response
    {
        $monat   = $request->input('month', now()->format('Y-m'));
        $monat   = $this->clampMonat($monat);
        $bericht = $this->service->forMonth($monat);
        $company = Company::first();

        $pdf = Pdf::loadView('pdf.kassenbericht', compact('bericht', 'company'))
            ->setPaper('a4', 'portrait');

        $filename = 'kassenbericht-' . $monat . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** Ensure the month string is a valid YYYY-MM and not in the future. */
    private function clampMonat(string $monat): string
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
            return now()->format('Y-m');
        }
        $current = now()->format('Y-m');
        return $monat <= $current ? $monat : $current;
    }
}
