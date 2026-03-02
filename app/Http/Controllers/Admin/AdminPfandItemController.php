<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Catalog\PfandItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPfandItemController extends Controller
{
    public function index(): View
    {
        $pfandItems = PfandItem::orderBy('bezeichnung')->get();
        return view('admin.pfand.index', compact('pfandItems'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'bezeichnung'     => ['required', 'string', 'max:150'],
            'pfand_typ'       => ['required', 'in:Einweg,Mehrweg'],
            'wert_brutto_eur' => ['required', 'numeric', 'min:0'],
        ]);
        $brutto = (int) round((float) $request->input('wert_brutto_eur') * 1_000_000);
        PfandItem::create([
            'bezeichnung'                         => $request->input('bezeichnung'),
            'pfand_typ'                           => $request->input('pfand_typ'),
            'wert_brutto_milli'                   => $brutto,
            'wert_netto_milli'                    => $brutto, // simplified: same as brutto for now
            'wiederverkaeufer_wert_brutto_milli'  => $brutto,
            'wiederverkaeufer_wert_netto_milli'   => $brutto,
            'active'                              => true,
        ]);
        return back()->with('success', 'Pfandposition angelegt.');
    }

    public function update(Request $request, PfandItem $pfandItem): JsonResponse|RedirectResponse
    {
        $request->validate([
            'bezeichnung'     => ['sometimes', 'required', 'string', 'max:150'],
            'pfand_typ'       => ['sometimes', 'required', 'in:Einweg,Mehrweg'],
            'wert_brutto_eur' => ['sometimes', 'required', 'numeric', 'min:0'],
            'active'          => ['sometimes', 'in:0,1'],
        ]);
        $data = [];
        if ($request->has('bezeichnung'))     $data['bezeichnung']                        = $request->input('bezeichnung');
        if ($request->has('pfand_typ'))       $data['pfand_typ']                          = $request->input('pfand_typ');
        if ($request->has('wert_brutto_eur')) {
            $milli = (int) round((float) $request->input('wert_brutto_eur') * 1_000_000);
            $data['wert_brutto_milli'] = $milli;
            $data['wert_netto_milli']  = $milli;
            $data['wiederverkaeufer_wert_brutto_milli'] = $milli;
            $data['wiederverkaeufer_wert_netto_milli']  = $milli;
        }
        if ($request->has('active')) $data['active'] = (bool) $request->input('active');
        $pfandItem->update($data);
        if ($request->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Pfandposition gespeichert.');
    }

    public function destroy(PfandItem $pfandItem): RedirectResponse
    {
        $pfandItem->delete();
        return back()->with('success', 'Pfandposition gelöscht.');
    }
}
