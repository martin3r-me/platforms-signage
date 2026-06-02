<?php

use Illuminate\Support\Facades\Route;
use Platform\Signage\Http\Controllers\MediaController;
use Platform\Signage\Http\Controllers\PlayerController;

// Player-Shell (Vollbild), die auf jedem TV-Browser geöffnet wird.
Route::get('/play', [PlayerController::class, 'show'])->name('signage.play');

// Signierte Auslieferung einer Mediendatei (Fallback wenn kein Cloud-Storage mit Temp-URLs).
Route::get('/media/{token}', [MediaController::class, 'show'])
    ->name('signage.media.show')
    ->middleware('signed');
