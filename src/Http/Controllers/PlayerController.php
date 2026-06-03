<?php

namespace Platform\Signage\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Liefert die Vollbild-Player-Shell aus. Die gesamte Laufzeit-Logik
 * (Registrierung, Pairing, Polling, Wiedergabe) steckt im Client-JS der View.
 */
class PlayerController
{
    public function show(): View
    {
        // URLs immer aus den Route-Namen ableiten – der API-Prefix (/api/signage)
        // wird vom ModuleRouter vergeben und darf nicht hartkodiert werden.
        return view('signage::player', [
            'registerUrl'         => route('signage.api.register'),
            'stateUrlTemplate'    => route('signage.api.screen.state', ['deviceToken' => '__TOKEN__']),
            'manifestUrlTemplate' => route('signage.api.screen.manifest', ['deviceToken' => '__TOKEN__']),
            'pollInterval'        => (int) config('signage.poll_interval_seconds', 10),
            'manifestRefresh'     => (int) config('signage.manifest_refresh_seconds', 21600),
        ]);
    }
}
