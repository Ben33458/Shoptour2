<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class StoreRentalCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth check is done in controller
    }

    public function rules(): array
    {
        return [
            'event_location_name'    => 'required|string|max:255',
            'event_location_street'  => 'required|string|max:255',
            'event_location_zip'     => 'required|string|max:10',
            'event_location_city'    => 'required|string|max:100',
            'event_contact_name'     => 'required|string|max:150',
            'event_contact_phone'    => 'required|string|max:50',
            'event_access_notes'     => 'nullable|string|max:1000',
            'event_setup_notes'      => 'nullable|string|max:1000',
            'event_has_power'        => 'boolean',
            'event_suitable_ground'  => 'boolean',
            'customer_notes'         => 'nullable|string|max:2000',
            'event_delivery_mode'    => 'required|in:delivery,self_pickup',
            'event_pickup_mode'      => 'required|in:pickup_by_us,self_return',
        ];
    }

    public function messages(): array
    {
        return [
            'event_location_name.required'   => 'Bitte gib den Namen des Veranstaltungsortes an.',
            'event_location_street.required' => 'Bitte gib die Straße an.',
            'event_location_zip.required'    => 'Bitte gib die Postleitzahl an.',
            'event_location_city.required'   => 'Bitte gib die Stadt an.',
            'event_contact_name.required'    => 'Bitte gib einen Ansprechpartner an.',
            'event_contact_phone.required'   => 'Bitte gib eine Telefonnummer an.',
            'event_delivery_mode.required'   => 'Bitte wähle die Lieferart.',
            'event_pickup_mode.required'     => 'Bitte wähle die Abholart.',
        ];
    }
}
