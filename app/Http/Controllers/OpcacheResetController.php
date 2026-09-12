<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpcacheResetController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expectedToken = trim((string) config('services.opcache_reset_token', ''));

        if ($expectedToken === '' || $request->query('token') !== $expectedToken) {
            abort(403);
        }

        if (! function_exists('opcache_reset')) {
            return response()->json(['opcache' => 'unavailable']);
        }

        $reset = opcache_reset();

        return response()->json(['opcache' => $reset ? 'reset' : 'failed']);
    }
}
