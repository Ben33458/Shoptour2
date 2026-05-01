<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PROJ-32: Validation for creating a new PurchaseOrder.
 */
class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by controller middleware
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('items')) {
            $this->merge([
                'items' => array_values(
                    array_filter(
                        $this->input('items', []),
                        fn ($item) => is_array($item)
                        && isset($item['product_id'])
                        && is_numeric($item['product_id'])
                        && (int) $item['product_id'] > 0
                    )
                ),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id'  => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'ordered_at'   => ['nullable', 'date'],
            'expected_at'  => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'notes'        => ['nullable', 'string', 'max:5000'],

            'items'                        => ['required', 'array', 'min:1'],
            'items.*.product_id'           => ['required', 'integer', 'exists:products,id'],
            'items.*.qty'                  => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_purchase_milli'  => ['required', 'integer', 'min:0'],
            'items.*.notes'                => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required'                     => 'Mindestens eine Position ist erforderlich.',
            'items.min'                          => 'Mindestens eine Position ist erforderlich.',
            'items.*.product_id.required'        => 'Bitte ein Produkt auswählen.',
            'items.*.qty.required'               => 'Bitte eine Menge angeben.',
            'items.*.qty.min'                    => 'Die Menge muss größer als 0 sein.',
            'items.*.unit_purchase_milli.required' => 'Bitte einen EK-Preis angeben.',
        ];
    }
}
