<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * is_admin is deliberately NOT fillable: admin rights can never be granted
     * through mass assignment (e.g. a registration form). Use makeAdmin().
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile_number',
        'password',
    ];

    // status is not fillable either: only the admin block/unblock actions change it.
    protected $attributes = [
        'is_admin' => false,
        'status' => 'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'status' => AccountStatus::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Explicitly grant admin rights (bypasses $fillable on purpose).
     */
    public function makeAdmin(): static
    {
        $this->forceFill(['is_admin' => true])->save();

        return $this;
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('is_admin', false);
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('is_admin', true);
    }
    /**
     * Plans the customer has joined (through customer_plans).
     * A customer may hold several plans — including the same plan more than once.
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'customer_plans')
            ->using(CustomerPlan::class)
            ->withPivot(['id', 'scheme_number', 'status', 'start_date', 'maturity_date', 'monthly_amount', 'duration_months', 'total_commitment'])
            ->withTimestamps();
    }

    /**
     * The customer's plan enrolments.
     */
    public function customerPlans(): HasMany
    {
        return $this->hasMany(CustomerPlan::class);
    }

    public function kycProfile(): HasOne
    {
        return $this->hasOne(KycProfile::class);
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function isBlocked(): bool
    {
        return $this->status === AccountStatus::Blocked;
    }

    /** Created by an admin and not yet activated, or deactivated by an admin. */
    public function isInactive(): bool
    {
        return $this->status === AccountStatus::Inactive;
    }

    /**
     * The single sign-in rule for customer accounts: only Active accounts may sign in.
     * Used by the customer login and the portal middleware — any future customer
     * API / token authentication must check this too.
     */
    public function canSignIn(): bool
    {
        return ($this->status ?? AccountStatus::Active)->canSignIn();
    }

    public function customerAuditLogs(): HasMany
    {
        return $this->hasMany(CustomerAuditLog::class)->latest('created_at')->latest('id');
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function goldTransactions(): HasMany
    {
        return $this->hasMany(GoldTransaction::class);
    }
}
