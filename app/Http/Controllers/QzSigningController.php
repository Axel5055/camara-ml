<?php

namespace App\Http\Controllers;

use App\Services\QzSigner;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QzSigningController extends Controller
{
    public function certificate(QzSigner $signer): Response
    {
        abort_unless($signer->isConfigured(), 404);

        return response($signer->certificate(), 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    public function sign(Request $request, QzSigner $signer): Response
    {
        abort_unless($signer->isConfigured(), 404);

        $validated = $request->validate(['request' => ['required', 'string', 'max:20000']]);

        return response($signer->sign($validated['request']), 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
