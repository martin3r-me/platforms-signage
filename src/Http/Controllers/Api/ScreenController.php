<?php

namespace Platform\Signage\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Services\PlayerManifestService;
use Platform\Signage\Support\EventBoardService;

class ScreenController
{
    public function __construct(private PlayerManifestService $manifests)
    {
    }

    /**
     * State + Heartbeat. Der Player pollt diesen Endpoint und lädt das Manifest
     * neu, sobald sich content_version ändert.
     */
    public function state(string $deviceToken): JsonResponse
    {
        $screen = SignageScreen::where('device_token', $deviceToken)->first();

        // Bildschirm gelöscht/unbekannt -> Player soll sich zurücksetzen.
        if (!$screen) {
            return response()->json(['status' => 'removed'], 200);
        }

        // Heartbeat ohne updated_at-Touch (kein content_version-Bump).
        // Im Admin-Vorschau-Modus (?preview=1) NICHT zählen – sonst gilt ein
        // ausgeschalteter Bildschirm fälschlich als online, sobald jemand die
        // Live-Vorschau öffnet.
        if (!request()->boolean('preview')) {
            $screen->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return response()->json([
            'status'          => $screen->status,
            'pairing_code'    => $screen->pairing_code,
            'name'            => $screen->name,
            'content_version' => (int) $screen->content_version,
        ]);
    }

    /**
     * Aufgelöstes Wiedergabe-Manifest (Visual + Musik) mit signierten URLs.
     */
    public function manifest(string $deviceToken): JsonResponse
    {
        $screen = SignageScreen::where('device_token', $deviceToken)->first();

        if (!$screen) {
            return response()->json(['status' => 'removed'], 200);
        }

        if ($screen->status !== 'active') {
            return response()->json([
                'status'       => $screen->status,
                'pairing_code' => $screen->pairing_code,
            ]);
        }

        return response()->json(array_merge(
            ['status' => 'active'],
            $this->manifests->resolve($screen)
        ));
    }

    /**
     * Daten fürs Veranstaltungs-Board (vom events-App-Frame des Players abgerufen).
     * Team ergibt sich aus dem Bildschirm; gefiltert nach Tagen/Status.
     */
    public function events(Request $request, string $deviceToken): JsonResponse
    {
        $screen = SignageScreen::where('device_token', $deviceToken)->first();
        if (!$screen || $screen->status !== 'active') {
            return response()->json(['available' => false, 'events' => []]);
        }

        $days = (int) $request->query('days', 1);
        $statuses = array_values(array_filter(explode(',', (string) $request->query('status', ''))));

        return response()->json([
            'available' => EventBoardService::available(),
            'events'    => EventBoardService::upcoming((int) $screen->team_id, $days, $statuses),
        ]);
    }
}
