<?php

namespace Platform\Signage\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Support\EventBoardService;
use Platform\Signage\Support\FleetBoardService;
use Platform\Signage\Support\KitaOffice\KitaOfficeConfig;
use Platform\Signage\Support\KitaOfficeBoardService;

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
        } elseif ($media->app_type === 'fleetmap') {
            $config['endpoint'] = route('signage.apps.fleetmap.data');
        } elseif ($media->app_type === 'kitaoffice') {
            $config['endpoint'] = route('signage.apps.kitaoffice.data', ['media' => $media->id]);
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

    /** JSON fürs Tourenplan-Board in der Vorschau (eingeloggter Editor als User). */
    public function fleetData(Request $request): JsonResponse
    {
        $connectionId = (int) $request->query('connection_id', 0) ?: null;

        return response()->json(FleetBoardService::board(auth()->user(), $connectionId, [
            'show_progress'  => $request->boolean('progress', true),
            'driver_message' => $request->boolean('notes', false),
            'date'           => $request->query('date'),
        ]));
    }

    /** JSON für die Fahrzeug-Karte in der Vorschau (eingeloggter Editor als User). */
    public function fleetVehiclesData(Request $request): JsonResponse
    {
        $connectionId = (int) $request->query('connection_id', 0) ?: null;

        return response()->json(FleetBoardService::liveVehicles(auth()->user(), $connectionId));
    }

    /**
     * JSON fürs Kita-Office-Board in der Vorschau (eingeloggter Editor als User).
     * Gespeicherte App: Konfiguration aus dem Record (team-gescoped). Live-Editor:
     * ungespeicherte Konfiguration als JSON in `cfg`, bereinigt wie beim Speichern.
     * Zugriff auf die Connections prüft die Integration anhand des eingeloggten Users.
     */
    public function kitaOfficeData(Request $request): JsonResponse
    {
        $user = auth()->user();
        $config = [];

        if ($request->filled('media')) {
            $media = SignageMedia::query()
                ->where('team_id', $user?->currentTeam?->id)
                ->where('app_type', 'kitaoffice')
                ->find((int) $request->query('media'));
            abort_unless($media, 404);
            $config = $media->config ?? [];
        } else {
            $decoded = json_decode((string) $request->query('cfg', ''), true);
            $config = is_array($decoded) ? $decoded : [];
        }

        return response()->json(KitaOfficeBoardService::board($user, KitaOfficeConfig::sanitize($config)));
    }
}
