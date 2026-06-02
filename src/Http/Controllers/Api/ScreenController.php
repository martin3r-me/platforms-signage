<?php

namespace Platform\Signage\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Services\PlayerManifestService;

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
        $screen = $this->find($deviceToken);

        // Heartbeat ohne updated_at-Touch (kein content_version-Bump).
        $screen->forceFill(['last_seen_at' => now()])->saveQuietly();

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
        $screen = $this->find($deviceToken);

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

    private function find(string $deviceToken): SignageScreen
    {
        $screen = SignageScreen::where('device_token', $deviceToken)->first();

        if (!$screen) {
            abort(404, 'Unbekanntes Gerät.');
        }

        return $screen;
    }
}
