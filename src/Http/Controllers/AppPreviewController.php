<?php

namespace Platform\Signage\Http\Controllers;

use Illuminate\Contracts\View\View;
use Platform\Signage\Models\SignageMedia;

/**
 * Rendert eine einzelne App (Uhr/Wetter) als eigenständige Seite – wird in der
 * Bibliothek als iframe-Vorschau eingebettet.
 */
class AppPreviewController
{
    public function show(SignageMedia $media): View
    {
        abort_unless(
            $media->isApp() && $media->team_id === auth()->user()?->currentTeam?->id,
            404
        );

        return view('signage::apps.preview', [
            'appType' => $media->app_type,
            'config'  => $media->config ?? [],
        ]);
    }
}
