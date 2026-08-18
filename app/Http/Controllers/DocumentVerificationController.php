<?php

namespace App\Http\Controllers;

use App\Models\DocumentVerification;

class DocumentVerificationController extends Controller
{
    public function show(string $reference)
    {
        $verification = DocumentVerification::where('reference', $reference)->first();

        return view('documents.verify', [
            'verification' => $verification,
            'reference' => $reference,
        ]);
    }
}
