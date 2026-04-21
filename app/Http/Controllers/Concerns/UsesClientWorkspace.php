<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ClientWorkspace;

trait UsesClientWorkspace
{
    /**
     * Utilisateur « métier » : dossier client en session (comptable) ou compte pivot entreprise (licence) ou compte connecté.
     */
    protected function workspaceUserId(): int
    {
        return ClientWorkspace::effectiveUserId();
    }

    /**
     * Périmètre des données agrégées (liste écritures, trésorerie…) : équipe licence = plusieurs id, sinon un seul.
     *
     * @return list<int>
     */
    protected function workspaceDataUserIds(): array
    {
        return ClientWorkspace::dataScopeUserIds();
    }

    /**
     * La ligne métier (user_id stocké) appartient au périmètre visible / modifiable.
     */
    protected function workspaceOwnsDataUserId(int $dataUserId): bool
    {
        return in_array($dataUserId, $this->workspaceDataUserIds(), true);
    }
}
