<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Gebinde;
use App\Models\Catalog\PfandSet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminGebindeController extends Controller
{
    public function index(): View
    {
        $gebindeList = Gebinde::with('pfandSet')->withCount('products')->orderBy('name')->get();
        $pfandSets = PfandSet::where('active', true)->orderBy('name')->get();
        return view('admin.gebinde.index', compact('gebindeList', 'pfandSets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'          => ['required', 'string', 'max:150'],
            'gebinde_type'  => ['required', 'string', 'max:50'],
            'material'      => ['nullable', 'in:PET,PEC,Glas'],
            'pfand_set_id'  => ['nullable', 'exists:pfand_sets,id'],
        ]);
        Gebinde::create([
            'name'         => $request->input('name'),
            'gebinde_type' => $request->input('gebinde_type'),
            'material'     => $request->input('material') ?: null,
            'pfand_set_id' => $request->input('pfand_set_id') ?: null,
            'active'       => true,
        ]);
        return back()->with('success', 'Gebinde angelegt.');
    }

    public function update(Request $request, Gebinde $gebinde): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:150'],
            'gebinde_type' => ['sometimes', 'required', 'string', 'max:50'],
            'material'     => ['sometimes', 'nullable', 'in:PET,PEC,Glas'],
            'pfand_set_id' => ['sometimes', 'nullable', 'exists:pfand_sets,id'],
            'active'       => ['sometimes', 'in:0,1'],
        ]);
        $data = $request->only(['name', 'gebinde_type', 'material', 'pfand_set_id', 'active']);
        if (array_key_exists('pfand_set_id', $data)) $data['pfand_set_id'] = $data['pfand_set_id'] ?: null;
        if (array_key_exists('active', $data)) $data['active'] = (bool) $data['active'];
        $gebinde->update($data);
        if ($request->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Gebinde gespeichert.');
    }

    public function destroy(Gebinde $gebinde): RedirectResponse
    {
        if ($gebinde->products()->exists()) {
            return back()->with('error', 'Gebinde kann nicht gelöscht werden – noch Produkte zugeordnet.');
        }
        $gebinde->delete();
        return back()->with('success', 'Gebinde gelöscht.');
    }
}
