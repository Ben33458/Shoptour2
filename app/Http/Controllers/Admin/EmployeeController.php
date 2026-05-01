<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Services\Employee\EmployeeMailService;
use App\Services\Reconcile\EmployeeReconcileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeReconcileService $reconcile,
        private readonly EmployeeMailService $mailer,
    ) {}
    public function index(): View
    {
        $employees = Employee::withTrashed()->orderBy('last_name')->get();

        // Build Ninox lookup map: employee_id => ninox_mitarbeiter row
        $ninoxIds = $employees->pluck('ninox_source_id')->filter()->values()->all();
        $ninoxData = [];

        if (! empty($ninoxIds)) {
            $ninoxRows = DB::table('ninox_mitarbeiter')
                ->whereIn('ninox_id', $ninoxIds)
                ->get()
                ->keyBy('ninox_id');

            foreach ($employees as $emp) {
                if ($emp->ninox_source_id && $ninoxRows->has((int) $emp->ninox_source_id)) {
                    $ninoxData[$emp->id] = $ninoxRows->get((int) $emp->ninox_source_id);
                }
            }
        }

        return view('admin.employees.index', compact('employees', 'ninoxData'));
    }

    public function create(): View
    {
        $maxNumber = Employee::withTrashed()
            ->whereNotNull('employee_number')
            ->get()
            ->map(fn ($e) => (int) $e->employee_number)
            ->max();

        $nextNumber = str_pad((string) (($maxNumber ?? 0) + 1), 4, '0', STR_PAD_LEFT);

        return view('admin.employees.form', [
            'employee'   => new Employee(['employee_number' => $nextNumber]),
            'ninox'      => null,
            'ninoxAlt'   => null,
            'sentEmails' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_number'           => 'required|string|max:20|unique:employees',
            'first_name'                => 'required|string|max:100',
            'last_name'                 => 'required|string|max:100',
            'email'                     => 'nullable|email|max:255|unique:employees',
            'phone'                     => 'nullable|string|max:30',
            'birth_date'                => 'nullable|date',
            'hire_date'                 => 'required|date',
            'role'                      => 'required|in:admin,manager,teamleader,employee',
            'employment_type'           => 'required|in:full_time,part_time,mini_job,intern',
            'weekly_hours'              => 'required|integer|min:1|max:60',
            'vacation_days_per_year'    => 'required|integer|min:0|max:60',
            'work_on_saturdays'         => 'nullable|boolean',
            'pin'                       => 'nullable|digits:4',
            'zustaendigkeit'            => 'nullable|array',
            'zustaendigkeit.*'          => 'string|max:100',
            // Extended fields
            'nickname'                  => 'nullable|string|max:100',
            'address_street'            => 'nullable|string|max:255',
            'address_zip'               => 'nullable|string|max:20',
            'address_city'              => 'nullable|string|max:100',
            'emergency_contact_name'    => 'nullable|string|max:255',
            'emergency_contact_phone'   => 'nullable|string|max:50',
            'clothing_size'             => 'nullable|string|max:20',
            'shoe_size'                 => 'nullable|string|max:20',
            'drivers_license_class'     => 'nullable|string|max:50',
            'drivers_license_expiry'    => 'nullable|date',
            'iban'                      => 'nullable|string|max:50',
            'notes_employee'            => 'nullable|string|max:2000',
        ]);

        if (isset($data['pin'])) {
            $data['pin_hash'] = Hash::make($data['pin']);
            unset($data['pin']);
        }
        $data['zustaendigkeit']   = $data['zustaendigkeit'] ?? [];
        $data['work_on_saturdays'] = $request->boolean('work_on_saturdays');

        Employee::create($data);

        return redirect()->route('admin.employees.index')->with('success', 'Mitarbeiter angelegt.');
    }

    public function edit(Employee $employee): View
    {
        $ninox    = null;
        $ninoxAlt = null;

        if ($employee->ninox_source_id) {
            $ninox = DB::table('ninox_mitarbeiter')
                ->where('ninox_id', $employee->ninox_source_id)
                ->first();
        }

        // Load alt DB data for the Ninox panel
        $altRaw = $this->findAltRecord($employee);
        if ($altRaw) {
            $ninoxAlt = (object) $altRaw;
        }

        $sentEmails = $employee->sentEmails()->latest()->get();

        return view('admin.employees.form', compact('employee', 'ninox', 'ninoxAlt', 'sentEmails'));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'employee_number'           => 'required|string|max:20|unique:employees,employee_number,' . $employee->id,
            'first_name'                => 'required|string|max:100',
            'last_name'                 => 'required|string|max:100',
            'email'                     => 'nullable|email|max:255|unique:employees,email,' . $employee->id,
            'phone'                     => 'nullable|string|max:30',
            'birth_date'                => 'nullable|date',
            'hire_date'                 => 'required|date',
            'leave_date'                => 'nullable|date|after_or_equal:hire_date',
            'role'                      => 'required|in:admin,manager,teamleader,employee',
            'employment_type'           => 'required|in:full_time,part_time,mini_job,intern',
            'weekly_hours'              => 'required|integer|min:1|max:60',
            'vacation_days_per_year'    => 'required|integer|min:0|max:60',
            'work_on_saturdays'         => 'nullable|boolean',
            'is_active'                 => 'boolean',
            'pin'                       => 'nullable|digits:4',
            'zustaendigkeit'            => 'nullable|array',
            'zustaendigkeit.*'          => 'string|max:100',
            // Extended fields
            'nickname'                  => 'nullable|string|max:100',
            'address_street'            => 'nullable|string|max:255',
            'address_zip'               => 'nullable|string|max:20',
            'address_city'              => 'nullable|string|max:100',
            'emergency_contact_name'    => 'nullable|string|max:255',
            'emergency_contact_phone'   => 'nullable|string|max:50',
            'clothing_size'             => 'nullable|string|max:20',
            'shoe_size'                 => 'nullable|string|max:20',
            'drivers_license_class'     => 'nullable|string|max:50',
            'drivers_license_expiry'    => 'nullable|date',
            'iban'                      => 'nullable|string|max:50',
            'notes_employee'            => 'nullable|string|max:2000',
        ]);

        if (isset($data['pin'])) {
            $data['pin_hash'] = Hash::make($data['pin']);
            unset($data['pin']);
        }
        $data['is_active']        = $request->boolean('is_active');
        $data['work_on_saturdays'] = $request->boolean('work_on_saturdays');
        $data['zustaendigkeit']   = $data['zustaendigkeit'] ?? [];

        $employee->update($data);

        return redirect()->route('admin.employees.index')->with('success', 'Mitarbeiter aktualisiert.');
    }

    /**
     * Copy available Ninox fields into the employee record.
     *
     * Priority (highest = wins):
     *   1. shoptour2 local DB  — source of truth, never auto-overwritten
     *   2. kehr DB (ninox_mitarbeiter, current Ninox)
     *   3. alt DB  (raw_records fadrrq8poh9b/D, old Ninox)
     *
     * Default mode (force=false): only fill fields that are empty locally.
     * Force mode (force=true):    overwrite existing fields from Ninox — requires
     *                             explicit confirmation in the UI.
     */
    public function syncNinox(Employee $employee, Request $request): RedirectResponse
    {
        if (! $employee->ninox_source_id) {
            return back()->with('error', 'Kein Ninox-Datensatz verknüpft.');
        }

        $force = (bool) $request->input('force', false);

        $updates = [];

        // Helper: only update if force, or if the local field is empty
        $maySet = fn (string $field, mixed $value) =>
            $force || empty($employee->$field) ? ($updates[$field] = $value) : null;

        // ── 1. alt DB (lowest priority — old Ninox) ───────────────────────
        $altRecord = $this->findAltRecord($employee);

        if ($altRecord) {
            $updates['ninox_alt_source_id'] = (string) $altRecord['_ninox_id'];

            $map = [
                'email'         => 'E-Mail',
                'phone'         => 'Telefon',
                'iban'          => 'IBAN',
                'address_zip'   => 'PLZ',
                'address_city'  => 'Ort',
                'clothing_size' => 'T-Shirt Größe',
                'shoe_size'     => 'Schuhgröße',
                'nickname'      => 'Spitzname',
            ];
            foreach ($map as $localField => $ninoxKey) {
                if (! empty($altRecord[$ninoxKey])) {
                    $maySet($localField, (string) $altRecord[$ninoxKey]);
                }
            }

            $street = trim(($altRecord['Strasse'] ?? '') . ' ' . ($altRecord['Hausnummer'] ?? ''));
            if ($street !== '') {
                $maySet('address_street', $street);
            }
            if (! empty($altRecord['Geburtsdatum'])) {
                $maySet('birth_date', $altRecord['Geburtsdatum']);
            }
            if (! empty($altRecord['beschäftigt seit'])) {
                $maySet('hire_date', $altRecord['beschäftigt seit']);
            }
            if (! empty($altRecord['Planstunden pro Woche'])) {
                $maySet('weekly_hours', (int) $altRecord['Planstunden pro Woche']);
            }
            if (! empty($altRecord['Art der Anstellung'])) {
                $type = match (mb_strtolower((string) $altRecord['Art der Anstellung'])) {
                    'vollzeit'                => 'full_time',
                    'teilzeit'                => 'part_time',
                    'minijob'                 => 'mini_job',
                    'praktikant', 'praktikum' => 'intern',
                    default                   => null,
                };
                if ($type) {
                    $maySet('employment_type', $type);
                }
            }
        }

        // ── 2. kehr DB (higher priority — current Ninox) ──────────────────
        $kehr = DB::table('ninox_mitarbeiter')
            ->where('ninox_id', $employee->ninox_source_id)
            ->first();

        if ($kehr && ! empty($kehr->spitzname)) {
            $maySet('nickname', $kehr->spitzname);
        }

        if (empty($updates)) {
            return back()->with('success', 'Alle Felder bereits befüllt — nichts zu übernehmen.');
        }

        $employee->update($updates);

        $visible = array_diff(array_keys($updates), ['ninox_alt_source_id']);
        $msg = empty($visible)
            ? 'Alt-DB verknüpft.'
            : 'Ninox-Daten übernommen: ' . implode(', ', $visible) . '.';

        return back()->with('success', $msg);
    }

    /**
     * Find the matching record in the alt Ninox DB.
     * Uses stored ninox_alt_source_id if available, falls back to name-match.
     *
     * @return array<string,mixed>|null
     */
    private function findAltRecord(Employee $employee): ?array
    {
        $altDbId = config('services.ninox.db_id_alt', 'fadrrq8poh9b');

        // Use stored ID first
        if ($employee->ninox_alt_source_id) {
            $rec = DB::table('ninox_raw_records')
                ->where('db_id', $altDbId)
                ->where('table_id', 'D')
                ->where('ninox_id', $employee->ninox_alt_source_id)
                ->where('is_latest', true)
                ->first();

            if ($rec) {
                $data = json_decode($rec->record_data, true) ?? [];
                $data['_ninox_id'] = $rec->ninox_id;
                return $data;
            }
        }

        // Fall back to name-match via service
        return $this->reconcile->findAltByName(
            (string) ($employee->first_name ?? ''),
            (string) ($employee->last_name ?? ''),
        );
    }

    public function resetOnboarding(Employee $employee): RedirectResponse
    {
        $employee->update([
            'onboarding_status'       => 'pending',
            'onboarding_completed_at' => null,
            'privacy_accepted_at'     => null,
        ]);

        // Invalidate any open tokens
        \App\Models\Employee\OnboardingToken::where('employee_id', $employee->id)
            ->whereNull('used_at')
            ->delete();

        return back()->with('success', 'Onboarding zurückgesetzt — Mitarbeiter kann sich erneut registrieren.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();
        return redirect()->route('admin.employees.index')->with('success', 'Mitarbeiter deaktiviert.');
    }
}
