<?php

use Illuminate\Support\Facades\Route;
use Platform\Signage\Http\Controllers\Api\RegisterController;
use Platform\Signage\Http\Controllers\Api\ScreenController;

// Erstmalige Geräte-Registrierung: erzeugt einen pending-Screen + Pairing-Code.
Route::post('/register', [RegisterController::class, 'register'])->name('signage.api.register');

// State/Heartbeat: liefert Status + content_version, aktualisiert last_seen_at.
Route::get('/screen/{deviceToken}', [ScreenController::class, 'state'])->name('signage.api.screen.state');

// Manifest: aufgelöste Playlist (Visual + Musik) mit signierten Medien-URLs.
Route::get('/screen/{deviceToken}/manifest', [ScreenController::class, 'manifest'])->name('signage.api.screen.manifest');
