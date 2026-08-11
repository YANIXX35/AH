<?php

namespace App\Policies;

use App\Models\CommercialProspection;
use App\Models\User;

class CommercialProspectionPolicy
{
    private function own(User $user, CommercialProspection $prospection): bool
    {
        return (int) $prospection->commercial_id === (int) $user->id;
    }

    public function view(User $user, CommercialProspection $prospection): bool
    {
        return ($user->is_platform_admin ?? false) || $this->own($user, $prospection);
    }

    public function update(User $user, CommercialProspection $prospection): bool
    {
        return $this->own($user, $prospection) && $prospection->isEditable();
    }

    public function delete(User $user, CommercialProspection $prospection): bool
    {
        return $this->own($user, $prospection) && $prospection->status === CommercialProspection::STATUS_DRAFT;
    }

    public function submit(User $user, CommercialProspection $prospection): bool
    {
        return $this->own($user, $prospection) && $prospection->isEditable();
    }
}
