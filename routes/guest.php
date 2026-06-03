<?php

use Illuminate\Support\Facades\Route;
use Platform\Signage\Http\Controllers\MediaController;
use Platform\Signage\Http\Controllers\PlayerController;

// Player-Shell (Vollbild), die auf jedem TV-Browser geöffnet wird.
Route::get('/play', [PlayerController::class, 'show'])->name('signage.play');

// Web-App-Manifest: ermöglicht "Zum Startbildschirm hinzufügen" im Vollbild-/
// Standalone-Modus (sofern der Browser/das TV es unterstützt -> ohne URL-Leiste).
Route::get('/play/manifest.webmanifest', function () {
    return response()->json([
        'name'             => 'Digital Signage Player',
        'short_name'       => 'Signage',
        'start_url'        => url('/signage/play'),
        'scope'            => url('/signage/play'),
        'display'          => 'fullscreen',
        'display_override' => ['fullscreen', 'standalone'],
        'orientation'      => 'landscape',
        'background_color' => '#000000',
        'theme_color'      => '#000000',
    ])->header('Content-Type', 'application/manifest+json');
})->name('signage.play.manifest');

// Signierte Auslieferung einer Mediendatei (Fallback wenn kein Cloud-Storage mit Temp-URLs).
Route::get('/media/{token}', [MediaController::class, 'show'])
    ->name('signage.media.show')
    ->middleware('signed');
