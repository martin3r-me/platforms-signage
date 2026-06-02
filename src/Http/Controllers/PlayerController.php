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
        return view('signage::player', [
            'registerUrl'     => route('signage.api.register'),
            'stateUrlBase'    => url('/signage/api/screen'),
            'pollInterval'    => (int) config('signage.poll_interval_seconds', 10),
        ]);
    }
}
