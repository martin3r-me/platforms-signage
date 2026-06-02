<?php

use Illuminate\Support\Facades\Route;
use Platform\Signage\Livewire\Dashboard;
use Platform\Signage\Livewire\Media\Index as MediaIndex;
use Platform\Signage\Livewire\Playlists\Index as PlaylistIndex;
use Platform\Signage\Livewire\Playlists\Show as PlaylistShow;
use Platform\Signage\Livewire\Screens\Index as ScreenIndex;
use Platform\Signage\Livewire\Screens\Show as ScreenShow;

Route::get('/', Dashboard::class)->name('signage.dashboard');

Route::get('/media', MediaIndex::class)->name('signage.media.index');

Route::get('/playlists', PlaylistIndex::class)->name('signage.playlists.index');
Route::get('/playlists/{playlist}', PlaylistShow::class)->name('signage.playlists.show');

Route::get('/screens', ScreenIndex::class)->name('signage.screens.index');
Route::get('/screens/{screen}', ScreenShow::class)->name('signage.screens.show');
