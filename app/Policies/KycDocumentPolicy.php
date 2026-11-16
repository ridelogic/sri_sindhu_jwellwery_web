<?php

namespace App\Policies;

use App\Models\KycDocument;
use App\Models\User;

/**
 * KYC documents: an admin may view any (for review); a customer only their own.
 */
class KycDocumentPolicy
{
    public function view(User $user, KycDocument $document): bool
    {
        return $user->isAdmin() || $user->id === $document->user_id;
    }
}
