<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;

class DocumentationController extends Controller
{
    /**
     * Télécharge la synthèse fonctionnelle et méthodes de calcul (PDF).
     */
    public function downloadSynthese()
    {
        $safeName = 'synthese-'.preg_replace('/[^a-z0-9\-]+/i', '-', config('app.name')).'-v'.config('app.version').'.pdf';

        return Pdf::loadView('documentation.synthese-application-pdf')
            ->setPaper('a4', 'portrait')
            ->download($safeName);
    }
}
