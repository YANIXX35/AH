<?php

namespace App\Policies;

use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Support\ClientWorkspace;

class TreasuryTransactionPolicy
{
    /**
     * Transaction rattachée au périmètre dossier (y compris collègues même licence entreprise).
     */
    public function own(User $user, TreasuryTransaction $transaction): bool
    {
        $ids = ClientWorkspace::dataScopeUserIds($user);

        return in_array((int) $transaction->user_id, $ids, true);
    }

    public function view(User $user, TreasuryTransaction $transaction): bool
    {
        return $this->own($user, $transaction);
    }

    public function update(User $user, TreasuryTransaction $transaction): bool
    {
        return $this->own($user, $transaction);
    }

    public function delete(User $user, TreasuryTransaction $transaction): bool
    {
        return $this->own($user, $transaction);
    }
}
