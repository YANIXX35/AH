<?php

namespace App\Support;

/**
 * Convertit le logo d'une entreprise (chemin sur le disque `public`) en
 * data URI base64 pour l'intégrer directement dans un PDF DomPDF — DomPDF ne
 * peut pas s'authentifier pour lire une URL protégée, et Apache renvoie 403
 * sur les URLs /storage/... en production quel que soit le contenu du
 * .htaccess, donc l'image doit être embarquée telle quelle dans le HTML
 * plutôt que référencée par une URL.
 */
class CompanyLogo
{
    public static function toDataUri(?string $storedPath): ?string
    {
        if (! $storedPath) {
            return null;
        }

        $absolutePath = storage_path('app/public/'.$storedPath);
        if (! file_exists($absolutePath)) {
            return null;
        }

        $mime = function_exists('finfo_open')
            ? (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $absolutePath) ?: 'image/png')
            : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolutePath));
    }
}
