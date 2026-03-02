<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCustomerGroupController extends Controller
{
    public function index(): View
    {
        $groups         = CustomerGroup::withCount('customers')->orderBy('name')->get();
        $defaultGroupId = AppSetting::getInt('default_customer_group_id', 0);
        return view('admin.customer-groups.index', compact('groups', 'defaultGroupId'));
    }

    /**
     * WP-21 – Set the default customer group for new registrations and guest pricing.
     */
    public function setDefault(CustomerGroup $customerGroup): RedirectResponse
    {
        AppSetting::set('default_customer_group_id', (string) $customerGroup->id);
        return back()->with('success', "'{$customerGroup->name}' ist jetzt die Standard-Kundengruppe.");
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'                              => ['required', 'string', 'max:150', 'unique:customer_groups,name'],
            'price_adjustment_type'             => ['required', 'in:none,fixed,percent'],
            'price_adjustment_fixed_eur'        => ['nullable', 'numeric'],
            'price_adjustment_percent_bp'       => ['nullable', 'integer', 'min:-10000', 'max:10000'],
            'is_business'                       => ['nullable', 'boolean'],
            'is_deposit_exempt'                 => ['nullable', 'boolean'],
        ]);
        CustomerGroup::create([
            'name'                                   => $request->input('name'),
            'price_adjustment_type'                  => $request->input('price_adjustment_type'),
            'price_adjustment_fixed_milli'           => (int) round((float) $request->input('price_adjustment_fixed_eur', 0) * 1_000_000),
            'price_adjustment_percent_basis_points'  => (int) $request->input('price_adjustment_percent_bp', 0),
            'is_business'                            => $request->boolean('is_business'),
            'is_deposit_exempt'                      => $request->boolean('is_deposit_exempt'),
            'active'                                 => true,
        ]);
        return back()->with('success', 'Kundengruppe angelegt.');
    }

    public function update(Request $request, CustomerGroup $customerGroup): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name'                        => ['sometimes', 'required', 'string', 'max:150', 'unique:customer_groups,name,' . $customerGroup->id],
            'price_adjustment_type'       => ['sometimes', 'required', 'in:none,fixed,percent'],
            'price_adjustment_fixed_eur'  => ['sometimes', 'nullable', 'numeric'],
            'price_adjustment_percent_bp' => ['sometimes', 'nullable', 'integer'],
            'is_business'                 => ['sometimes', 'in:0,1'],
            'is_deposit_exempt'           => ['sometimes', 'in:0,1'],
            'active'                      => ['sometimes', 'in:0,1'],
        ]);
        $data = [];
        if ($request->has('name'))                        $data['name'] = $request->input('name');
        if ($request->has('price_adjustment_type'))       $data['price_adjustment_type'] = $request->input('price_adjustment_type');
        if ($request->has('price_adjustment_fixed_eur'))  $data['price_adjustment_fixed_milli'] = (int) round((float) $request->input('price_adjustment_fixed_eur') * 1_000_000);
        if ($request->has('price_adjustment_percent_bp')) $data['price_adjustment_percent_basis_points'] = (int) $request->input('price_adjustment_percent_bp');
        if ($request->has('is_business'))                 $data['is_business'] = (bool) $request->input('is_business');
        if ($request->has('is_deposit_exempt'))           $data['is_deposit_exempt'] = (bool) $request->input('is_deposit_exempt');
        if ($request->has('active'))                      $data['active'] = (bool) $request->input('active');
        $customerGroup->update($data);
        if ($request->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Kundengruppe gespeichert.');
    }

    public function destroy(CustomerGroup $customerGroup): RedirectResponse
    {
        if ($customerGroup->customers()->exists()) {
            return back()->with('error', 'Kundengruppe kann nicht gelöscht werden – noch Kunden zugeordnet.');
        }
        $customerGroup->delete();
        return back()->with('success', 'Kundengruppe gelöscht.');
    }
}
