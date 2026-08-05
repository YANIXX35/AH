<?php

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;
use App\Support\ClientWorkspace;

class PayrollRunPolicy
{
    /**
     * Lot de paie rattaché au périmètre dossier (y compris collègues même licence entreprise).
     */
    public function own(User $user, PayrollRun $payroll): bool
    {
        $ids = ClientWorkspace::dataScopeUserIds($user);

        return in_array((int) $payroll->user_id, $ids, true);
    }

    public function view(User $user, PayrollRun $payroll): bool
    {
        return $this->own($user, $payroll);
    }

    public function update(User $user, PayrollRun $payroll): bool
    {
        return $this->own($user, $payroll);
    }

    public function delete(User $user, PayrollRun $payroll): bool
    {
        return $this->own($user, $payroll);
    }
}
