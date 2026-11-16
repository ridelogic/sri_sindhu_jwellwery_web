<?php

namespace App\Models;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use Database\Factories\KycDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A KYC document attached to a KYC profile.
 *
 * document_number is encrypted at rest; document_number and file_path are
 * hidden from serialisation so they never appear in public responses.
 */
class KycDocument extends Model
{
    /** @use HasFactory<KycDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kyc_profile_id',
        'document_type',
        'document_number',
        'file_path',
        'mime_type',
        'size_bytes',
        'status',
        'verified_at',
    ];

    protected $hidden = [
        'document_number',
        'file_path',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => KycDocumentType::class,
            'document_number' => 'encrypted',
            'status' => KycDocumentStatus::class,
            'verified_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep user_id consistent with the owning KYC profile.
        static::saving(function (KycDocument $document) {
            $profileUserId = KycProfile::query()->whereKey($document->kyc_profile_id)->value('user_id');

            if ($profileUserId === null) {
                return; // The foreign key constraint will reject an unknown profile.
            }

            if ($document->user_id === null) {
                $document->user_id = $profileUserId;
            } elseif ((int) $document->user_id !== (int) $profileUserId) {
                throw new LogicException('A KYC document must belong to the same user as its KYC profile.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kycProfile(): BelongsTo
    {
        return $this->belongsTo(KycProfile::class);
    }

    /** Short file kind for display, derived from the validated MIME type (never the client filename). */
    public function fileKind(): string
    {
        return match ($this->mime_type) {
            'application/pdf' => 'PDF',
            'image/jpeg' => 'JPG',
            'image/png' => 'PNG',
            default => 'File',
        };
    }

    public function humanSize(): ?string
    {
        if ($this->size_bytes === null) {
            return null;
        }

        return $this->size_bytes >= 1048576
            ? number_format($this->size_bytes / 1048576, 1).' MB'
            : max(1, (int) round($this->size_bytes / 1024)).' KB';
    }
}
