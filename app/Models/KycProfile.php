<?php

namespace App\Models;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use Database\Factories\KycProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * A customer's KYC details (one per user).
 *
 * Security:
 *  - pan_number is encrypted at rest (Laravel "encrypted" cast).
 *  - Only the LAST 4 digits of Aadhaar are ever stored.
 *  - Sensitive fields are hidden from arrays / JSON so they cannot leak
 *    into public responses or logs via serialisation.
 */
class KycProfile extends Model
{
    /** @use HasFactory<KycProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'date_of_birth',
        'gender',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'country',
        'pan_number',
        'aadhaar_last4',
        'kyc_status',
        'verified_at',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected $hidden = [
        'full_name',
        'pan_number',
        'aadhaar_last4',
        'date_of_birth',
        'address_line1',
        'address_line2',
        'pincode',
    ];

    protected $attributes = [
        'kyc_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'pan_number' => 'encrypted',
            'kyc_status' => KycStatus::class,
            'verified_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (KycProfile $profile) {
            if ($profile->aadhaar_last4 !== null && ! preg_match('/^\d{4}$/', (string) $profile->aadhaar_last4)) {
                // Guards against a full Aadhaar number ever being stored here.
                throw new InvalidArgumentException('aadhaar_last4 must be exactly the last 4 digits.');
            }

            if ($profile->pan_number !== null) {
                $profile->pan_number = strtoupper(trim($profile->pan_number));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(KycAuditLog::class)->latest('created_at')->latest('id');
    }

    public function documentOfType(KycDocumentType $type): ?KycDocument
    {
        return $this->documents->first(fn (KycDocument $document) => $document->document_type === $type);
    }

    /**
     * Masked Aadhaar for display, e.g. "XXXX XXXX 1234".
     */
    public function maskedAadhaar(): ?string
    {
        return $this->aadhaar_last4 ? 'XXXX XXXX '.$this->aadhaar_last4 : null;
    }

    public function isEditableByCustomer(): bool
    {
        return $this->kyc_status->isEditableByCustomer();
    }

    /**
     * Masked PAN for display, e.g. "XXXXXX234F".
     */
    public function maskedPan(): ?string
    {
        return $this->pan_number ? str_repeat('X', max(0, strlen($this->pan_number) - 4)).substr($this->pan_number, -4) : null;
    }

    public function isVerified(): bool
    {
        return $this->kyc_status === KycStatus::Verified;
    }
}
