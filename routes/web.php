<?php

use Illuminate\Support\Facades\Route;
use Platform\Signage\Http\Controllers\AppPreviewController;
use Platform\Signage\Livewire\Apps\Clock as ClockApp;
use Platform\Signage\Livewire\Apps\Weather as WeatherApp;
use Platform\Signage\Livewire\Dashboard;
use Platform\Signage\Livewire\Media\Index as MediaIndex;
use Platform\Signage\Livewire\Playlists\Index as PlaylistIndex;
use Platform\Signage\Livewire\Playlists\Show as PlaylistShow;
use Platform\Signage\Livewire\Schedules\Index as ScheduleIndex;
use Platform\Signage\Livewire\Schedules\Show as ScheduleShow;
use Platform\Signage\Livewire\Screens\Index as ScreenIndex;
use Platform\Signage\Livewire\Screens\Show as ScreenShow;

Route::get('/', Dashboard::class)->name('signage.dashboard');

Route::get('/media', MediaIndex::class)->name('signage.media.index');
Route::get('/media/{media}/edit', \Platform\Signage\Livewire\Media\Edit::class)->name('signage.media.edit');

// App-Editoren – erstellen + bearbeiten
Route::get('/apps/clock', ClockApp::class)->name('signage.apps.clock.create');
Route::get('/apps/clock/{media}', ClockApp::class)->name('signage.apps.clock.edit');
Route::get('/apps/weather', WeatherApp::class)->name('signage.apps.weather.create');
Route::get('/apps/weather/{media}', WeatherApp::class)->name('signage.apps.weather.edit');
Route::get('/apps/menu', \Platform\Signage\Livewire\Apps\Menu::class)->name('signage.apps.menu.create');
Route::get('/apps/menu/{media}', \Platform\Signage\Livewire\Apps\Menu::class)->name('signage.apps.menu.edit');
Route::get('/apps/events', \Platform\Signage\Livewire\Apps\Events::class)->name('signage.apps.events.create');
Route::get('/apps/events/{media}', \Platform\Signage\Livewire\Apps\Events::class)->name('signage.apps.events.edit');
// Daten fürs Veranstaltungs-Board in der Editor-/Bibliotheks-Vorschau (session-auth).
Route::get('/apps/events-data', [AppPreviewController::class, 'eventsData'])->name('signage.apps.events.data');

// Eigenständige App-Vorschau (iframe-Einbettung in der Bibliothek)
Route::get('/apps/preview/{media}', [AppPreviewController::class, 'show'])->name('signage.apps.preview');

// Live-Vorschau (leer, wird per postMessage vom App-Editor gesteuert)
Route::view('/apps/preview-live', 'signage::apps.preview-live')->name('signage.apps.preview-live');

// Musik-Stream einbinden / bearbeiten
Route::get('/streams/new', \Platform\Signage\Livewire\Streams\Edit::class)->name('signage.streams.create');
Route::get('/streams/{media}/edit', \Platform\Signage\Livewire\Streams\Edit::class)->name('signage.streams.edit');

// Website einbinden / bearbeiten
Route::get('/websites/new', \Platform\Signage\Livewire\Websites\Edit::class)->name('signage.websites.create');
Route::get('/websites/{media}/edit', \Platform\Signage\Livewire\Websites\Edit::class)->name('signage.websites.edit');

Route::get('/playlists', PlaylistIndex::class)->name('signage.playlists.index');
Route::get('/playlists/{playlist}', PlaylistShow::class)->name('signage.playlists.show');

Route::get('/schedules', ScheduleIndex::class)->name('signage.schedules.index');
Route::get('/schedules/{schedule}', ScheduleShow::class)->name('signage.schedules.show');

Route::get('/screens', ScreenIndex::class)->name('signage.screens.index');
Route::get('/screens/{screen}', ScreenShow::class)->name('signage.screens.show');

Route::get('/groups', \Platform\Signage\Livewire\Groups\Index::class)->name('signage.groups.index');
Route::get('/groups/{group}', \Platform\Signage\Livewire\Groups\Show::class)->name('signage.groups.show');

Route::get('/reports/playback', \Platform\Signage\Livewire\Reports\PlaybackReport::class)->name('signage.reports.playback');

// Fire-TV-Kiosk-APK – Direkt-Download für eingeloggte Admins (ohne Code).
Route::get('/firetv/app.apk', [\Platform\Signage\Http\Controllers\FireTvApkController::class, 'download'])
    ->name('signage.firetv.apk');
