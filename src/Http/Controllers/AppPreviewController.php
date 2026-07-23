<?php

namespace Platform\Signage\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Support\EventBoardService;
use Platform\Signage\Support\FleetBoardService;

/**
 * Rendert eine einzelne App (Uhr/Wetter/Menü/Veranstaltungen) als eigenständige
 * Seite – wird in der Bibliothek als iframe-Vorschau eingebettet.
 */
class AppPreviewController
{
    public function show(SignageMedia $media): View
    {
        abort_unless(
            $media->isApp() && $media->team_id === auth()->user()?->currentTeam?->id,
            404
        );

        $config = $media->config ?? [];

        // Dynamische Apps brauchen eine Daten-Endpoint-URL (hier session-authentifiziert,
        // damit in der Vorschau kein Geräte-Token nötig ist).
        if ($media->app_type === 'events') {
            $config['endpoint'] = route('signage.apps.events.data');
        } elseif ($media->app_type === 'dedefleet') {
            $config['endpoint'] = route('signage.apps.dedefleet.data');
        }

        return view('signage::apps.preview', [
            'appType' => $media->app_type,
            'config'  => $config,
        ]);
    }

    /** JSON fürs Veranstaltungs-Board in der Vorschau (Team aus der Session). */
    public function eventsData(Request $request): JsonResponse
    {
        $teamId = (int) (auth()->user()?->currentTeam?->id ?? 0);
        $days = (int) $request->query('days', 1);
        $statuses = array_values(array_filter(explode(',', (string) $request->query('status', ''))));

        return response()->json([
            'available' => EventBoardService::available(),
            'events'    => EventBoardService::upcoming($teamId, $days, $statuses),
        ]);
    }

    /** JSON fürs Tourenplan-Board in der Vorschau (Team aus der Session). */
    public function fleetData(Request $request): JsonResponse
    {
        $teamId = (int) (auth()->user()?->currentTeam?->id ?? 0);
        $connectionId = (int) $request->query('connection_id', 0) ?: null;

        return response()->json(FleetBoardService::board($teamId, $connectionId, [
            'show_progress' => $request->boolean('progress', true),
            'date'          => $request->query('date'),
        ]));
    }
}
