<?php

use Illuminate\Support\Facades\Route;
use Platform\Signage\Http\Controllers\AppPreviewController;
use Platform\Signage\Livewire\Apps\Clock as ClockApp;
use Platform\Signage\Livewire\Apps\Weather as WeatherApp;
use Platform\Signage\Livewire\Dashboard;
use Platform\Signage\Livewire\Media\Index as MediaIndex;
use Platform\Signage\Livewire\Playlists\Index as PlaylistIndex;
use Platform\Signage\Livewire\Playlists\Show as PlaylistShow;
use Platform\Signage\Livewire\Screens\Index as ScreenIndex;
use Platform\Signage\Livewire\Screens\Show as ScreenShow;

Route::get('/', Dashboard::class)->name('signage.dashboard');

Route::get('/media', MediaIndex::class)->name('signage.media.index');

// App-Editoren – erstellen + bearbeiten
Route::get('/apps/clock', ClockApp::class)->name('signage.apps.clock.create');
Route::get('/apps/clock/{media}', ClockApp::class)->name('signage.apps.clock.edit');
Route::get('/apps/weather', WeatherApp::class)->name('signage.apps.weather.create');
Route::get('/apps/weather/{media}', WeatherApp::class)->name('signage.apps.weather.edit');

// Eigenständige App-Vorschau (iframe-Einbettung in der Bibliothek)
Route::get('/apps/preview/{media}', [AppPreviewController::class, 'show'])->name('signage.apps.preview');

// Live-Vorschau (leer, wird per postMessage vom App-Editor gesteuert)
Route::view('/apps/preview-live', 'signage::apps.preview-live')->name('signage.apps.preview-live');

// Musik-Stream bearbeiten
Route::get('/streams/{media}/edit', \Platform\Signage\Livewire\Streams\Edit::class)->name('signage.streams.edit');

Route::get('/playlists', PlaylistIndex::class)->name('signage.playlists.index');
Route::get('/playlists/{playlist}', PlaylistShow::class)->name('signage.playlists.show');

Route::get('/screens', ScreenIndex::class)->name('signage.screens.index');
Route::get('/screens/{screen}', ScreenShow::class)->name('signage.screens.show');
