<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Services\Catalog\JugendschutzService;
use App\Services\Payments\IbanValidator;
use App\Services\Shop\CartService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PROJ-4: Validates checkout form submission.
 *
 * Handles both home_delivery and pickup delivery types,
 * new address creation inline, and payment method selection.
 */
class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth check is done by route middleware
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cartItems = app(CartService::class)->items(auth()->user());
        $minAge    = JugendschutzService::cartMinAge($cartItems);

        return [
            // Legal consent
            'agb_accepted'  => ['required', 'accepted'],
            'age_confirmed' => $minAge > 0 ? ['required', 'accepted'] : ['nullable'],

            // Step 1: Delivery type
            'delivery_type' => ['required', Rule::in(['home_delivery', 'pickup'])],

            // Step 2a: Delivery address (home_delivery).
            // BUG-18 fix: value can be "new" (inline form) or a positive integer (existing address ID).
            'delivery_address_id' => [
                'required_if:delivery_type,home_delivery',
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && $value !== 'new' && $value !== 'event_location' && (!ctype_digit((string) $value) || (int) $value <= 0)) {
                        $fail('Ungültige Lieferadresse.');
                    }
                },
            ],

            // Step 2a: New address fields (when delivery_address_id = "new")
            'new_address.street'       => ['required_if:delivery_address_id,new', 'nullable', 'string', 'max:200'],
            'new_address.house_number' => ['nullable', 'string', 'max:20'],
            'new_address.zip'          => ['required_if:delivery_address_id,new', 'nullable', 'string', 'max:10'],
            'new_address.city'         => ['required_if:delivery_address_id,new', 'nullable', 'string', 'max:100'],
            'new_address.first_name'   => ['nullable', 'string', 'max:100'],
            'new_address.last_name'    => ['nullable', 'string', 'max:100'],
            'new_address.company'      => ['nullable', 'string', 'max:200'],
            'new_address.phone'        => ['nullable', 'string', 'max:50'],

            // Drop-off location (for delivery addresses)
            'drop_off_location'        => ['nullable', Rule::in(['keller', 'einfahrt', 'eg', 'garage', 'og1', 'sonstiges'])],
            'drop_off_location_custom' => ['nullable', 'required_if:drop_off_location,sonstiges', 'string', 'max:500'],
            'leave_at_door'            => ['nullable', 'boolean'],

            // Event location as delivery address
            'event_location_delivery_id' => ['nullable', 'integer', 'exists:event_locations,id'],

            // Step 2b: Pickup warehouse (pickup)
            'pickup_warehouse_id' => ['required_if:delivery_type,pickup', 'nullable', 'integer', 'exists:warehouses,id'],

            // Step 3: Delivery date (home_delivery, not required for rental-only orders)
            'delivery_date' => ['required_if:delivery_type,home_delivery', 'required_without_all:has_rental_items,pickup_date', 'nullable', 'date', 'after:today'],

            // Step 3: Tour assignment (home_delivery only)
            'tour_id' => ['nullable', 'integer', 'exists:regular_delivery_tours,id'],

            // Step 3: Pickup date + time slot (pickup only)
            'pickup_date'      => ['required_if:delivery_type,pickup', 'nullable', 'date', 'after:today'],
            'pickup_time_from' => ['required_if:delivery_type,pickup', 'nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'pickup_time_to'   => ['required_if:delivery_type,pickup', 'nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],

            // Step 4: Payment method
            'payment_method' => ['required', Rule::in(['stripe', 'paypal', 'sepa', 'invoice', 'cash', 'ec'])],

            // SEPA mandate fields (required when sepa selected and no existing mandate used)
            'sepa_use_existing'   => ['nullable', 'boolean'],
            'sepa_iban'           => [
                Rule::requiredIf(fn () => $this->isSepaNewMandate()),
                'nullable', 'string', 'max:34',
                function (string $attr, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }
                    if (! app(IbanValidator::class)->isValid($value)) {
                        $fail('Die eingegebene IBAN ist ungültig. Bitte prüfe die Eingabe.');
                    }
                },
            ],
            'sepa_account_holder' => [
                Rule::requiredIf(fn () => $this->isSepaNewMandate()),
                'nullable', 'string', 'max:100',
            ],

            // Step 5: Customer notes
            'customer_notes' => ['nullable', 'string', 'max:1000'],

            // Event fields (required when rental items are in cart)
            'has_rental_items'       => ['nullable', 'boolean'],
            'event_location_name'    => ['nullable', 'string', 'max:255'],
            'event_location_street'  => ['nullable', 'string', 'max:255'],
            'event_location_zip'     => ['nullable', 'string', 'max:10'],
            'event_location_city'    => ['nullable', 'string', 'max:100'],
            'event_contact_name'     => ['required_if:has_rental_items,1', 'nullable', 'string', 'max:150'],
            'event_contact_phone'    => ['required_if:has_rental_items,1', 'nullable', 'string', 'max:50'],
            'event_delivery_mode'    => ['required_if:has_rental_items,1', 'nullable', Rule::in(['delivery', 'self_pickup'])],
            'event_pickup_mode'      => ['required_if:has_rental_items,1', 'nullable', Rule::in(['pickup_by_us', 'self_return'])],
            'event_access_notes'     => ['nullable', 'string', 'max:1000'],
            'event_setup_notes'      => ['nullable', 'string', 'max:1000'],
            'event_has_power'        => ['nullable', 'boolean'],
            'event_suitable_ground'  => ['nullable', 'boolean'],
        ];
    }

    /** True when SEPA is selected and customer is entering a new mandate (not reusing existing). */
    private function isSepaNewMandate(): bool
    {
        return $this->input('payment_method') === 'sepa'
            && ! filter_var($this->input('sepa_use_existing'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agb_accepted.required'         => 'Bitte akzeptiere die AGB und Widerrufsbelehrung.',
            'agb_accepted.accepted'         => 'Bitte akzeptiere die AGB und Widerrufsbelehrung.',
            'age_confirmed.required'        => 'Bitte bestätige dein Mindestalter für den Kauf alkoholischer Getränke.',
            'age_confirmed.accepted'        => 'Bitte bestätige dein Mindestalter für den Kauf alkoholischer Getränke.',
            'delivery_type.required'        => 'Bitte waehle eine Lieferart.',
            'delivery_type.in'              => 'Ungueltige Lieferart.',
            'delivery_address_id.required_if' => 'Bitte waehle eine Lieferadresse.',
            'pickup_warehouse_id.required_if' => 'Bitte waehle einen Abholort.',
            'pickup_warehouse_id.exists'     => 'Der gewaehlte Abholort existiert nicht.',
            'delivery_date.required_if'      => 'Bitte waehle einen Liefertermin.',
            'delivery_date.date'             => 'Ungueltiges Datum.',
            'delivery_date.after'            => 'Der Liefertermin muss in der Zukunft liegen.',
            'pickup_date.required_if'        => 'Bitte waehle ein Abholdatum.',
            'pickup_date.after'              => 'Das Abholdatum muss in der Zukunft liegen.',
            'pickup_time_from.required_if'   => 'Bitte waehle ein Zeitfenster.',
            'payment_method.required'        => 'Bitte waehle eine Zahlungsmethode.',
            'payment_method.in'              => 'Ungueltige Zahlungsmethode.',
            'customer_notes.max'             => 'Die Kundennotiz darf maximal 1000 Zeichen lang sein.',
            'new_address.street.required_if' => 'Bitte gib eine Strasse an.',
            'new_address.zip.required_if'    => 'Bitte gib eine PLZ an.',
            'new_address.city.required_if'   => 'Bitte gib eine Stadt an.',
            'tour_id.exists'                      => 'Die gewaehlte Tour existiert nicht.',
            'event_location_name.required_if'     => 'Bitte gib den Namen des Veranstaltungsortes an.',
            'event_location_street.required_if'   => 'Bitte gib die Straße des Veranstaltungsortes an.',
            'event_location_zip.required_if'      => 'Bitte gib die PLZ des Veranstaltungsortes an.',
            'event_location_city.required_if'     => 'Bitte gib den Ort des Veranstaltungsortes an.',
            'event_contact_name.required_if'      => 'Bitte gib einen Ansprechpartner vor Ort an.',
            'event_contact_phone.required_if'     => 'Bitte gib eine Telefonnummer für den Ansprechpartner an.',
            'event_delivery_mode.required_if'          => 'Bitte wähle die Lieferart für die Leihartikel.',
            'event_pickup_mode.required_if'            => 'Bitte wähle die Rückgabeart für die Leihartikel.',
            'drop_off_location_custom.required_if'     => 'Bitte beschreibe den Abstellort (Sonstiges).',
        ];
    }
}
