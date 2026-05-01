<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate via session in controller
    }

    public function rules(): array
    {
        return [
            // Persönliche Daten
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'birth_date'   => 'required|date|before:today',
            'nickname'     => 'nullable|string|max:100',

            // Kontakt
            'email'        => 'required|email|max:255',
            'phone'        => 'required|string|max:30',

            // Adresse
            'address_street' => 'required|string|max:200',
            'address_zip'    => 'required|string|max:10',
            'address_city'   => 'required|string|max:100',

            // Bankdaten
            'iban'           => ['required', 'string', 'max:34', function ($attr, $value, $fail) {
                $iban = strtoupper(preg_replace('/\s+/', '', $value));
                if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{4}\d{7}([A-Z0-9]?){0,16}$/', $iban)) {
                    $fail('Die IBAN hat kein gültiges Format.');
                }
            }],

            // Notfallkontakt
            'emergency_contact_name'  => 'required|string|max:200',
            'emergency_contact_phone' => 'required|string|max:30',

            // Optionale Felder
            'clothing_size'          => 'nullable|string|max:20',
            'shoe_size'              => 'nullable|string|max:10',
            'drivers_license_class'  => 'nullable|string|max:20',
            'drivers_license_expiry' => 'nullable|date|after:today',
            'notes_employee'         => 'nullable|string|max:2000',

            // Personalnummer + PIN
            'employee_number' => 'required|digits:4',
            'pin'             => 'required|digits:4',
            'pin_confirmation'=> 'required|same:pin',

            // Zustimmungen
            'privacy_accepted'   => 'required|accepted',
            'data_correct'       => 'required|accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'privacy_accepted.accepted' => 'Du musst der Datenschutzerklärung zustimmen.',
            'data_correct.accepted'     => 'Bitte bestätige die Richtigkeit deiner Angaben.',
            'pin.digits'                => 'Die PIN muss genau 4 Ziffern haben.',
            'pin_confirmation.same'     => 'Die PINs stimmen nicht überein.',
            'employee_number.digits'    => 'Die Personalnummer muss genau 4 Ziffern haben.',
            'birth_date.before'         => 'Das Geburtsdatum muss in der Vergangenheit liegen.',
        ];
    }

    /** Normalize IBAN: strip spaces and uppercase. */
    protected function prepareForValidation(): void
    {
        if ($this->has('iban')) {
            $this->merge([
                'iban' => strtoupper(preg_replace('/\s+/', '', $this->iban ?? '')),
            ]);
        }
    }
}
