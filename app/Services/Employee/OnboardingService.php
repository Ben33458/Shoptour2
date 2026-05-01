<?php

declare(strict_types=1);

namespace App\Services\Employee;

use App\Mail\OnboardingVerificationMail;
use App\Models\Employee\Employee;
use App\Models\Employee\OnboardingToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class OnboardingService
{
    public function __construct(private readonly SystemLogService $log) {}

    // ── Step 1: E-Mail prüfen ─────────────────────────────────────────────────

    /**
     * Find an importable employee by email.
     * Only employees whose onboarding has not been completed are considered.
     */
    public function findByEmail(string $email): ?Employee
    {
        return Employee::where('email', strtolower(trim($email)))
            ->whereNotIn('onboarding_status', ['active'])
            ->first();
    }

    // ── Step 2: Verifikations-Token senden ───────────────────────────────────

    /**
     * Issue a new token and send the verification email.
     */
    public function sendVerification(Employee $employee, ?string $ip = null): OnboardingToken
    {
        // Invalidate old tokens
        OnboardingToken::where('employee_id', $employee->id)
            ->whereNull('used_at')
            ->delete();

        $rawToken = Str::random(48);
        $code     = (string) random_int(100000, 999999);

        $token = OnboardingToken::create([
            'employee_id' => $employee->id,
            'token_hash'  => hash('sha256', $rawToken),
            'code'        => $code,
            'expires_at'  => now()->addHours(24),
            'ip_address'  => $ip,
        ]);

        Mail::to($employee->email)->send(
            new OnboardingVerificationMail($employee, $rawToken, $code)
        );

        $this->log->log('onboarding.email_sent', null, $employee->id, 'Employee', $employee->id, [
            'ip' => $ip,
        ]);

        return $token;
    }

    // ── Step 3a: Per Link verifizieren ───────────────────────────────────────

    /**
     * Verify via URL token.  Returns the employee on success.
     */
    public function verifyByToken(string $rawToken): ?Employee
    {
        $hash  = hash('sha256', $rawToken);
        $token = OnboardingToken::where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            return null;
        }

        $token->update(['used_at' => now()]);

        $this->log->log('onboarding.verified_by_link', null, $token->employee_id, 'Employee', $token->employee_id);

        return $token->employee;
    }

    // ── Step 3b: Per Code verifizieren ───────────────────────────────────────

    /**
     * Verify via numeric code.  Returns true on success.
     */
    public function verifyByCode(Employee $employee, string $code): bool
    {
        $token = OnboardingToken::where('employee_id', $employee->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('code', $code)
            ->first();

        if (! $token) {
            return false;
        }

        $token->update(['used_at' => now()]);

        $this->log->log('onboarding.verified_by_code', null, $employee->id, 'Employee', $employee->id);

        return true;
    }

    // ── Step 4: Daten speichern ───────────────────────────────────────────────

    /**
     * Save onboarding data entered by the employee.
     */
    public function saveData(Employee $employee, array $data): void
    {
        $fillable = [
            'first_name', 'last_name', 'birth_date',
            'phone', 'email',
            'address_street', 'address_zip', 'address_city',
            'iban',
            'emergency_contact_name', 'emergency_contact_phone',
            'nickname', 'clothing_size', 'shoe_size',
            'drivers_license_class', 'drivers_license_expiry',
            'notes_employee',
        ];

        $update = array_intersect_key($data, array_flip($fillable));

        if (! empty($data['privacy_accepted'])) {
            $update['privacy_accepted_at'] = now();
        }

        $employee->update($update);

        $this->log->log('onboarding.data_saved', null, $employee->id, 'Employee', $employee->id);
    }

    // ── Step 5: Personalnummer + PIN setzen ──────────────────────────────────

    /**
     * Set employee_number and PIN.
     * Throws RuntimeException on validation failure.
     */
    public function setCredentials(Employee $employee, string $employeeNumber, string $pin): void
    {
        // 4-digit validation
        if (! preg_match('/^\d{4}$/', $pin)) {
            throw new RuntimeException('Die PIN muss genau 4 Ziffern haben.');
        }
        if (! preg_match('/^\d{4}$/', $employeeNumber)) {
            throw new RuntimeException('Die Personalnummer muss genau 4 Ziffern haben.');
        }
        if ($pin === $employeeNumber) {
            throw new RuntimeException('PIN und Personalnummer dürfen nicht identisch sein.');
        }

        // Uniqueness check (skip own record)
        $exists = Employee::where('employee_number', $employeeNumber)
            ->where('id', '!=', $employee->id)
            ->exists();
        if ($exists) {
            throw new RuntimeException('Diese Personalnummer ist bereits vergeben.');
        }

        $employee->update([
            'employee_number' => $employeeNumber,
            'pin_hash'        => Hash::make($pin),
        ]);

        $this->log->log('onboarding.pin_set', null, $employee->id, 'Employee', $employee->id);
        $this->log->log('onboarding.employee_number_set', null, $employee->id, 'Employee', $employee->id, [
            'employee_number' => $employeeNumber,
        ]);
    }

    // ── Step 6: Zur Prüfung einreichen ───────────────────────────────────────

    /**
     * Submit onboarding for admin review.
     */
    public function submit(Employee $employee): void
    {
        if ($employee->onboarding_status === 'pending_review') {
            return; // already submitted
        }

        $employee->update([
            'onboarding_status'       => 'pending_review',
            'onboarding_completed_at' => now(),
        ]);

        $this->log->log('onboarding.submitted', null, $employee->id, 'Employee', $employee->id);
    }

    // ── Admin: Freigabe ───────────────────────────────────────────────────────

    /**
     * Approve and activate an employee.
     */
    public function approve(Employee $employee, int $adminUserId): void
    {
        $employee->update([
            'onboarding_status' => 'active',
            'is_active'         => true,
        ]);

        $this->log->log('onboarding.approved', $adminUserId, $employee->id, 'Employee', $employee->id);
    }

    /**
     * Reject onboarding (send back to pending).
     */
    public function reject(Employee $employee, int $adminUserId, string $reason = ''): void
    {
        $employee->update([
            'onboarding_status'       => 'pending',
            'onboarding_completed_at' => null,
        ]);

        $this->log->log('onboarding.rejected', $adminUserId, $employee->id, 'Employee', $employee->id, [
            'reason' => $reason,
        ]);
    }
}
