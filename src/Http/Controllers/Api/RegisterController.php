<?php

namespace Platform\Signage\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Platform\Signage\Services\ScreenPairingService;

class RegisterController
{
    public function __construct(private ScreenPairingService $pairing)
    {
    }

    /**
     * Registriert ein neues Gerät und liefert device_token + Pairing-Code zurück.
     */
    public function register(): JsonResponse
    {
        $screen = $this->pairing->register();

        return response()->json([
            'device_token' => $screen->device_token,
            'pairing_code' => $screen->pairing_code,
            'status'       => $screen->status,
        ]);
    }
}
