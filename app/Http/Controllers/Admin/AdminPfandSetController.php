<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\PfandItem;
use App\Models\Catalog\PfandSet;
use App\Models\Catalog\PfandSetComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * WP-20 (Erweiterung) – Pfandset-Verwaltung.
 *
 * Routes:
 *   GET    /admin/pfand-sets                              index()
 *   POST   /admin/pfand-sets                              store()
 *   GET    /admin/pfand-sets/{pfandSet}                   show()
 *   PATCH  /admin/pfand-sets/{pfandSet}                   update()
 *   DELETE /admin/pfand-sets/{pfandSet}                   destroy()
 *   POST   /admin/pfand-sets/{pfandSet}/components        storeComponent()
 *   DELETE /admin/pfand-sets/{pfandSet}/components/{comp} destroyComponent()
 */
class AdminPfandSetController extends Controller
{
    // =========================================================================
    // CRUD
    // =========================================================================

    /**
     * GET /admin/pfand-sets
     */
    public function index(): View
    {
        $pfandSets = PfandSet::withCount('components')
            ->withCount('gebinde')
            ->orderBy('name')
            ->get();

        return view('admin.pfand-sets.index', compact('pfandSets'));
    }

    /**
     * POST /admin/pfand-sets
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        PfandSet::create([
            'name'   => $request->input('name'),
            'active' => true,
        ]);

        return back()->with('success', 'Pfandset angelegt.');
    }

    /**
     * GET /admin/pfand-sets/{pfandSet}
     * Detail-Seite mit Komponentenverwaltung.
     */
    public function show(PfandSet $pfandSet): View
    {
        $pfandSet->load([
            'components.pfandItem',
            'components.childPfandSet',
        ]);

        $pfandItems  = PfandItem::where('active', true)->orderBy('bezeichnung')->get();
        $allSets     = PfandSet::where('id', '!=', $pfandSet->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('admin.pfand-sets.show', compact('pfandSet', 'pfandItems', 'allSets'));
    }

    /**
     * PATCH /admin/pfand-sets/{pfandSet}
     * Inline-Edit: name, active.
     */
    public function update(Request $request, PfandSet $pfandSet): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            $data = [];

            if ($request->has('name')) {
                $request->validate(['name' => ['required', 'string', 'max:150']]);
                $data['name'] = $request->input('name');
            }

            if ($request->has('active')) {
                $data['active'] = (bool) $request->input('active');
            }

            if (! empty($data)) {
                $pfandSet->update($data);
            }

            return response()->json(['ok' => true]);
        }

        // Full-form (unused currently, kept for completeness)
        $request->validate(['name' => ['required', 'string', 'max:150']]);
        $pfandSet->update([
            'name'   => $request->input('name'),
            'active' => $request->boolean('active'),
        ]);

        return back()->with('success', 'Pfandset gespeichert.');
    }

    /**
     * DELETE /admin/pfand-sets/{pfandSet}
     */
    public function destroy(PfandSet $pfandSet): RedirectResponse
    {
        // Guard: still in use by Gebinde?
        if ($pfandSet->gebinde()->count() > 0) {
            return back()->with('error',
                'Pfandset kann nicht gelöscht werden – es ist noch '
                . $pfandSet->gebinde()->count() . ' Gebinde(n) zugeordnet.'
            );
        }

        // Guard: referenced as child in another set's components?
        $parentCount = PfandSetComponent::where('child_pfand_set_id', $pfandSet->id)->count();
        if ($parentCount > 0) {
            return back()->with('error',
                'Pfandset kann nicht gelöscht werden – es wird noch in '
                . $parentCount . ' anderen Pfandset(s) als Untermenge verwendet.'
            );
        }

        $pfandSet->components()->delete();
        $pfandSet->delete();

        return redirect()->route('admin.pfand-sets.index')
            ->with('success', 'Pfandset gelöscht.');
    }

    // =========================================================================
    // Component management
    // =========================================================================

    /**
     * POST /admin/pfand-sets/{pfandSet}/components
     * Adds a PfandItem or a child PfandSet as a component line.
     */
    public function storeComponent(Request $request, PfandSet $pfandSet): RedirectResponse
    {
        $request->validate([
            'component_type' => ['required', 'in:item,set'],
            'pfand_item_id'  => ['required_if:component_type,item', 'nullable', 'exists:pfand_items,id'],
            'child_set_id'   => ['required_if:component_type,set', 'nullable', 'exists:pfand_sets,id'],
            'qty'            => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        // Prevent circular reference (set referencing itself)
        if ($request->input('component_type') === 'set'
            && (int) $request->input('child_set_id') === $pfandSet->id) {
            return back()->with('error', 'Ein Pfandset kann sich nicht selbst als Untermenge enthalten.');
        }

        if ($request->input('component_type') === 'item') {
            PfandSetComponent::create([
                'pfand_set_id'       => $pfandSet->id,
                'pfand_item_id'      => $request->input('pfand_item_id'),
                'child_pfand_set_id' => null,
                'qty'                => (int) $request->input('qty'),
            ]);
        } else {
            PfandSetComponent::create([
                'pfand_set_id'       => $pfandSet->id,
                'pfand_item_id'      => null,
                'child_pfand_set_id' => $request->input('child_set_id'),
                'qty'                => (int) $request->input('qty'),
            ]);
        }

        return back()->with('success', 'Komponente hinzugefügt.');
    }

    /**
     * DELETE /admin/pfand-sets/{pfandSet}/components/{component}
     */
    public function destroyComponent(PfandSet $pfandSet, PfandSetComponent $component): RedirectResponse
    {
        // Make sure this component actually belongs to the given set
        abort_if($component->pfand_set_id !== $pfandSet->id, 404);

        $component->delete();

        return back()->with('success', 'Komponente entfernt.');
    }
}
