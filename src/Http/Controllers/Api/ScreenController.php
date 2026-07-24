<?php

namespace Platform\Signage\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageProofOfPlay;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Services\PlayerManifestService;
use Platform\Signage\Support\EventBoardService;
use Platform\Signage\Support\FleetBoardService;

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

    /**
     * Daten fürs Tourenplan-Board (vom dedefleet-App-Frame des Players abgerufen).
     * Team ergibt sich aus dem Bildschirm; Connection/Optionen kommen als Query-Param
     * aus der App-Config.
     */
    public function fleet(Request $request, string $deviceToken): JsonResponse
    {
        $screen = SignageScreen::where('device_token', $deviceToken)->first();
        if (!$screen || $screen->status !== 'active') {
            return response()->json(['available' => false, 'tours' => []]);
        }

        // Connection + Ersteller-User werden serverseitig aus dem App-Record aufgelöst
        // (nie vom Gerät!). Die Media-Kennung ist im Manifest-Endpoint hinterlegt und
        // wird aufs Team des Bildschirms eingegrenzt.
        $media = SignageMedia::query()
            ->where('team_id', $screen->team_id)
            ->where('app_type', 'dedefleet')
            ->where('id', (int) $request->query('media'))
            ->first();

        if (!$media) {
            return response()->json(['available' => false, 'tours' => []]);
        }

        $config = $media->config ?? [];

        return response()->json(FleetBoardService::board(
            $media->user,
            isset($config['connection_id']) ? (int) $config['connection_id'] : null,
            [
                'show_progress' => (bool) ($config['show_progress'] ?? true),
                'date'          => $request->query('date'),
            ],
        ));
    }

    /**
     * Proof-of-Play: der Player meldet gebündelt, welche Medien wann liefen.
     * Erwartet { plays: [ { media_id, played_at, seconds } ] }.
     */
    public function recordPlays(Request $request, string $deviceToken): JsonResponse
    {
        $screen = SignageScreen::where('device_token', $deviceToken)->first();
        if (!$screen || $screen->status !== 'active') {
            return response()->json(['ok' => false], 200);
        }

        $plays = $request->input('plays', []);
        if (!is_array($plays)) {
            return response()->json(['ok' => false], 200);
        }

        $now = now();
        $rows = [];
        foreach (array_slice($plays, 0, 500) as $p) {
            $mediaId = isset($p['media_id']) ? (int) $p['media_id'] : 0;
            if ($mediaId <= 0) {
                continue;
            }
            $playedAt = $now;
            if (!empty($p['played_at'])) {
                try {
                    $playedAt = \Illuminate\Support\Carbon::parse($p['played_at']);
                } catch (\Throwable $e) {
                    $playedAt = $now;
                }
            }
            $rows[] = [
                'team_id'    => $screen->team_id,
                'screen_id'  => $screen->id,
                'media_id'   => $mediaId,
                'played_at'  => $playedAt,
                'seconds'    => isset($p['seconds']) ? max(0, min(86400, (int) $p['seconds'])) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            SignageProofOfPlay::insert($rows);
        }

        return response()->json(['ok' => true, 'stored' => count($rows)]);
    }
}
