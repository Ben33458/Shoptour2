<?php
namespace App\Models\Employee;

use App\Enums\ShiftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Driver\CashRegister;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'employee_number', 'first_name', 'last_name', 'email', 'phone',
        'birth_date', 'hire_date', 'leave_date', 'role', 'employment_type',
        'weekly_hours', 'work_on_saturdays', 'vacation_days_per_year', 'is_active', 'pin_hash', 'zustaendigkeit',
        // Ninox
        'ninox_source_id', 'ninox_source_table', 'ninox_alt_source_id',
        // Onboarding
        'onboarding_status', 'onboarding_completed_at', 'privacy_accepted_at',
        'iban', 'emergency_contact_name', 'emergency_contact_phone',
        'address_street', 'address_zip', 'address_city',
        'nickname', 'clothing_size', 'shoe_size',
        'drivers_license_class', 'drivers_license_expiry', 'notes_employee',
        'cash_register_id',
    ];

    protected $casts = [
        'birth_date'               => 'date',
        'hire_date'                => 'date',
        'leave_date'               => 'date',
        'drivers_license_expiry'   => 'date',
        'onboarding_completed_at'  => 'datetime',
        'privacy_accepted_at'      => 'datetime',
        'is_active'                => 'boolean',
        'work_on_saturdays'        => 'boolean',
        'zustaendigkeit'           => 'array',
    ];

    protected $hidden = ['pin_hash', 'iban'];

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function cashRegister(): BelongsTo { return $this->belongsTo(CashRegister::class); }
    public function shifts(): HasMany { return $this->hasMany(Shift::class); }
    public function timeEntries(): HasMany { return $this->hasMany(TimeEntry::class); }
    public function vacationRequests(): HasMany { return $this->hasMany(VacationRequest::class); }
    public function vacationBalances(): HasMany { return $this->hasMany(VacationBalance::class); }
    public function availabilityBlocks(): HasMany { return $this->hasMany(AvailabilityBlock::class); }
    public function tasks(): HasMany { return $this->hasMany(EmployeeTask::class, 'assigned_to'); }
    public function sentEmails(): HasMany { return $this->hasMany(SentEmployeeEmail::class); }

    public function onboardingTokens(): HasMany
    {
        return $this->hasMany(OnboardingToken::class);
    }

    public function loginSecurity(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LoginSecurity::class);
    }

    public function scopeActive($query) { return $query->where('is_active', true); }

    public function scopeOnboarding($query)
    {
        return $query->where('onboarding_status', 'pending_review');
    }

    public function isFullyOnboarded(): bool
    {
        return in_array($this->onboarding_status, ['approved', 'active']);
    }

    public function canUseTimeclock(): bool
    {
        return $this->onboarding_status === 'active' && $this->is_active && $this->pin_hash !== null;
    }
}
