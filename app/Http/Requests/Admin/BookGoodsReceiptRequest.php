<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PROJ-32: Validation for booking goods receipt on a PurchaseOrder.
 */
class BookGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by controller middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id'   => ['required', 'integer', 'exists:warehouses,id'],
            'received'       => ['required', 'array', 'min:1'],
            'received.*'     => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'received.required' => 'Bitte mindestens eine gelieferte Menge angeben.',
            'received.*.min'    => 'Die Menge darf nicht negativ sein.',
        ];
    }
}
