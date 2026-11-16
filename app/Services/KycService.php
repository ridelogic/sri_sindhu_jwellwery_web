<?php

namespace App\Services;

use App\Enums\KycAuditAction;
use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Models\KycAuditLog;
use App\Models\KycDocument;
use App\Notifications\NotificationMessages;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The KYC workflow:
 *
 *   pending (not submitted / draft) ──submit──▶ submitted (pending review)
 *   submitted ──approve──▶ verified        submitted ──reject──▶ rejected
 *   rejected ──edit + submit──▶ submitted
 *
 * Every transition runs in a transaction with the profile row locked, and the
 * current status is re-checked inside the lock, so two admins (or a double
 * click) cannot apply conflicting decisions.
 *
 * Documents live on the private "kyc" disk under server-generated names. The
 * customer's original filename is never stored or used.
 *
 * Nothing here logs identity data; the audit trail records IDs and actions only.
 */
class KycService
{
    public const DISK = 'kyc';

    public const GENDERS = ['female' => 'Female', 'male' => 'Male', 'other' => 'Other'];

    /** Documents collected in Stage 5 (keys are KycDocumentType values). */
    public const DOCUMENTS = [
        'aadhaar' => ['label' => 'Aadhaar / Identity Document', 'required' => true, 'hint' => 'Aadhaar card (front and back). A masked Aadhaar is preferred.'],
        'pan' => ['label' => 'PAN Card', 'required' => true, 'hint' => 'A clear copy of your PAN card.'],
        'address_proof' => ['label' => 'Address Proof', 'required' => false, 'hint' => 'Optional — only if your Aadhaar address is different.'],
    ];

    private const EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** The customer's profile, or an unsaved one (status "pending") if none exists yet. */
    public function profileFor(User $customer): KycProfile
    {
        return $customer->kycProfile()->with('documents')->first()
            ?? new KycProfile(['user_id' => $customer->id, 'full_name' => $customer->name, 'country' => 'India']);
    }

    /**
     * Save details and documents; optionally submit for review.
     *
     * $actor is the administrator entering the KYC on the customer's behalf (null =
     * the customer themself). The same rules apply either way: only a not-submitted
     * or rejected KYC can be changed, and submitting only ever moves it to
     * "submitted" (pending review) — it is never approved here. Approval stays a
     * separate review step (approve()/reject()).
     *
     * @param  array<string, mixed>  $details  validated customer-editable fields
     * @param  array<string, UploadedFile>  $uploads  keyed by document type
     */
    public function save(User $customer, array $details, array $uploads, bool $submit, ?User $actor = null): KycProfile
    {
        $this->assertCustomer($customer);

        if ($actor !== null && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['kyc' => 'Only an administrator can enter KYC on behalf of a customer.']);
        }

        $stored = [];   // new files written during this request
        $replaced = []; // old files to remove once the database commit succeeds

        try {
            $profile = DB::transaction(function () use ($customer, $details, $uploads, $submit, $actor, &$stored, &$replaced) {
                // Serialise per customer: the profile may not exist yet, so lock the user row.
                User::query()->whereKey($customer->id)->lockForUpdate()->first();

                $profile = KycProfile::query()->where('user_id', $customer->id)->lockForUpdate()->first()
                    ?? new KycProfile(['user_id' => $customer->id, 'country' => 'India']);

                if ($profile->exists && ! $profile->isEditableByCustomer()) {
                    throw ValidationException::withMessages(['kyc' => $actor
                        ? 'This KYC is '.strtolower($profile->kyc_status->label()).' and cannot be changed. Review it from the KYC page instead.'
                        : $this->lockedMessage($profile->kyc_status)]);
                }

                // Identity numbers left blank keep the value already on file.
                foreach (['aadhaar_last4', 'pan_number'] as $field) {
                    if (blank($details[$field] ?? null)) {
                        unset($details[$field]);
                    }
                }

                $profile->fill($details);
                $profile->save();

                foreach ($uploads as $type => $file) {
                    $path = $this->storeFile($customer, $file);
                    $stored[] = $path;

                    $document = $profile->documents()->where('document_type', $type)->first();
                    if ($document?->file_path) {
                        $replaced[] = $document->file_path;
                    }

                    ($document ?? new KycDocument(['kyc_profile_id' => $profile->id, 'user_id' => $customer->id, 'document_type' => $type]))
                        ->fill([
                            'file_path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size_bytes' => $file->getSize(),
                            'status' => KycDocumentStatus::Pending,
                            'verified_at' => null,
                        ])->save();
                }

                if ($submit) {
                    $profile->load('documents');
                    $this->assertComplete($profile);

                    $profile->forceFill([
                        'kyc_status' => KycStatus::Submitted,
                        'submitted_at' => now(),
                        'reviewed_at' => null,
                        'reviewed_by' => null,
                        'rejection_reason' => null,
                        'verified_at' => null,
                    ])->save();

                    $this->audit($profile, array_filter([
                        'actor_id' => ($actor ?? $customer)->id,
                        'action' => KycAuditAction::Submitted,
                        'note' => $actor ? 'Entered by an administrator on behalf of the customer.' : null,
                    ]));
                } elseif ($actor) {
                    // Admin draft: recorded for the audit trail (no customer notification).
                    $this->audit($profile, ['actor_id' => $actor->id, 'action' => KycAuditAction::EnteredByAdmin]);
                }

                return $profile;
            });
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($stored);

            throw $e;
        }

        Storage::disk(self::DISK)->delete($replaced);

        return $profile->load('documents');
    }

    public function approve(KycProfile $profile, User $admin): KycProfile
    {
        return $this->review($profile, $admin, function (KycProfile $locked) use ($admin) {
            $locked->forceFill([
                'kyc_status' => KycStatus::Verified,
                'verified_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'rejection_reason' => null,
            ])->save();

            $locked->documents()->update(['status' => KycDocumentStatus::Verified->value, 'verified_at' => now()]);
            $this->audit($locked, ['actor_id' => $admin->id, 'action' => KycAuditAction::Approved]);
        });
    }

    public function reject(KycProfile $profile, User $admin, string $reason): KycProfile
    {
        return $this->review($profile, $admin, function (KycProfile $locked) use ($admin, $reason) {
            $locked->forceFill([
                'kyc_status' => KycStatus::Rejected,
                'verified_at' => null,
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'rejection_reason' => $reason,
            ])->save();

            $this->audit($locked, ['actor_id' => $admin->id, 'action' => KycAuditAction::Rejected, 'note' => $reason]);
        });
    }

    /** Stream a document privately. Callers must authorise access first. */
    public function documentResponse(KycDocument $document, bool $download = false): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);
        abort_unless($document->file_path && $disk->exists($document->file_path), 404);

        $extension = self::EXTENSIONS[$document->mime_type] ?? pathinfo($document->file_path, PATHINFO_EXTENSION);
        $name = 'kyc-'.Str::slug($document->document_type->label()).'.'.$extension;

        return $disk->response($document->file_path, $name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ], $download ? 'attachment' : 'inline');
    }

    /**
     * Fields and documents still needed before the KYC can be submitted.
     *
     * @return list<string>
     */
    public function missingForSubmission(KycProfile $profile): array
    {
        $labels = [
            'full_name' => 'Full name', 'date_of_birth' => 'Date of birth', 'gender' => 'Gender',
            'address_line1' => 'Address', 'city' => 'City', 'state' => 'State', 'pincode' => 'Pincode',
            'aadhaar_last4' => 'Aadhaar last 4 digits', 'pan_number' => 'PAN',
        ];

        $missing = collect($labels)->filter(fn ($label, $field) => blank($profile->{$field}))->values();

        foreach (self::DOCUMENTS as $type => $slot) {
            if ($slot['required'] && ! $profile->documentOfType(KycDocumentType::from($type))) {
                $missing->push($slot['label']);
            }
        }

        return $missing->all();
    }

    private function review(KycProfile $profile, User $admin, callable $apply): KycProfile
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages(['kyc' => 'Only an administrator can review KYC.']);
        }

        return DB::transaction(function () use ($profile, $apply) {
            $locked = KycProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if (! $locked->kyc_status->isAwaitingReview()) {
                throw ValidationException::withMessages([
                    'kyc' => 'This KYC is '.strtolower($locked->kyc_status->label()).' and cannot be reviewed now.',
                ]);
            }

            $apply($locked);

            return $locked->fresh(['documents', 'reviewer']);
        });
    }

    /**
     * Record an audit entry and notify the customer once the transaction
     * commits. The notification is keyed by the audit entry, so each real
     * decision notifies exactly once; a notification failure never affects the KYC decision.
     */
    private function audit(KycProfile $profile, array $attributes): KycAuditLog
    {
        $log = $profile->auditLogs()->create($attributes);

        if (! $log->action->notifiesCustomer()) {
            return $log;
        }

        app(NotificationService::class)->afterCommit(function () use ($profile, $log) {
            app(NotificationService::class)->notify($profile->user()->firstOrFail(), NotificationMessages::kyc($log));
        });

        return $log;
    }

    private function assertComplete(KycProfile $profile): void
    {
        $missing = $this->missingForSubmission($profile);

        if ($missing !== []) {
            throw ValidationException::withMessages(['kyc' => 'Please complete: '.implode(', ', $missing).'.']);
        }
    }

    private function assertCustomer(User $customer): void
    {
        if ($customer->isAdmin()) {
            throw ValidationException::withMessages(['kyc' => 'Administrator accounts do not have a KYC profile.']);
        }
    }

    private function lockedMessage(KycStatus $status): string
    {
        return $status === KycStatus::Verified
            ? 'Your KYC has been verified and can no longer be changed online. Please contact our team if your details have changed.'
            : 'Your KYC is currently under review and cannot be changed.';
    }

    /** Server-generated name; the extension comes from the detected MIME type, never the client filename. */
    private function storeFile(User $customer, UploadedFile $file): string
    {
        $extension = self::EXTENSIONS[$file->getMimeType()] ?? throw ValidationException::withMessages(['documents' => 'Upload a PDF, JPG or PNG file.']);

        return $file->storeAs((string) $customer->id, Str::uuid()->toString().'.'.$extension, self::DISK);
    }
}
