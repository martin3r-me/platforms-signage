<?php

use Illuminate\Support\Facades\Route;
use Platform\Signage\Http\Controllers\Api\RegisterController;
use Platform\Signage\Http\Controllers\Api\ScreenController;

// Erstmalige Geräte-Registrierung: erzeugt einen pending-Screen + Pairing-Code.
// Gedrosselt, damit der öffentliche Endpoint nicht zum massenhaften Anlegen von
// Karteileichen missbraucht werden kann (siehe auch signage:prune-screens).
Route::post('/register', [RegisterController::class, 'register'])
    ->middleware('throttle:20,1')
    ->name('signage.api.register');

// State/Heartbeat: liefert Status + content_version, aktualisiert last_seen_at.
// Pro device_token gedrosselt (siehe RateLimiter 'signage-device' im ServiceProvider).
Route::get('/screen/{deviceToken}', [ScreenController::class, 'state'])
    ->middleware('throttle:signage-device')
    ->name('signage.api.screen.state');

// Manifest: aufgelöste Playlist (Visual + Musik) mit signierten Medien-URLs.
Route::get('/screen/{deviceToken}/manifest', [ScreenController::class, 'manifest'])
    ->middleware('throttle:signage-device')
    ->name('signage.api.screen.manifest');

// Daten fürs Veranstaltungs-Board (events-App).
Route::get('/screen/{deviceToken}/events', [ScreenController::class, 'events'])
    ->middleware('throttle:signage-device')
    ->name('signage.api.screen.events');

// Daten fürs Tourenplan-Board (dedefleet-App).
Route::get('/screen/{deviceToken}/fleet', [ScreenController::class, 'fleet'])
    ->middleware('throttle:signage-device')
    ->name('signage.api.screen.fleet');

// Proof-of-Play: gebündelte Wiedergabe-Meldungen des Players.
Route::post('/screen/{deviceToken}/played', [ScreenController::class, 'recordPlays'])
    ->middleware('throttle:signage-device')
    ->name('signage.api.screen.played');
