<?php

/**
 * Digital Signage Module Configuration
 *
 * @see Platform\Core\PlatformCore::registerModule()
 */

return [
    'routing' => [
        'mode' => env('SIGNAGE_MODE', 'path'),
        'prefix' => 'signage',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'signage.dashboard',
        'icon'  => 'heroicon-o-tv',
        'order' => 90,
    ],

    'sidebar' => [
        [
            'group' => 'Übersicht',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'signage.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
            ],
        ],
        [
            'group' => 'Inhalte',
            'items' => [
                [
                    'label' => 'Medien',
                    'route' => 'signage.media.index',
                    'icon'  => 'heroicon-o-photo',
                ],
                [
                    'label' => 'Wiedergabelisten',
                    'route' => 'signage.playlists.index',
                    'icon'  => 'heroicon-o-queue-list',
                ],
                [
                    'label' => 'Zeitpläne',
                    'route' => 'signage.schedules.index',
                    'icon'  => 'heroicon-o-clock',
                ],
            ],
        ],
        [
            'group' => 'Geräte',
            'items' => [
                [
                    'label' => 'Bildschirme',
                    'route' => 'signage.screens.index',
                    'icon'  => 'heroicon-o-computer-desktop',
                ],
            ],
        ],
    ],

    /**
     * Wie lange Bilder/Dokument-Seiten standardmäßig angezeigt werden (Sekunden).
     */
    'default_image_duration' => 10,

    /**
     * Ab wann ein Bildschirm als offline gilt (Sekunden seit last_seen_at).
     */
    'offline_after_seconds' => 60,

    /**
     * Polling-Intervall des Players (Sekunden).
     */
    'poll_interval_seconds' => 10,

    /**
     * Wie oft der Player das Manifest unabhängig von Änderungen neu lädt (Sekunden).
     * Hält die signierten Medien-URLs frisch (TTL der URLs ist 12 h). Standard 6 h.
     */
    'manifest_refresh_seconds' => 21600,

    /**
     * Maximale Upload-Größe in KB (für Bilder/Videos/Audio/Dokumente).
     * Standard 500 MB. Hebt Livewires Standard-Temp-Upload-Limit (12 MB) an.
     * ACHTUNG: PHP (upload_max_filesize/post_max_size) und der Webserver
     * (z.B. nginx client_max_body_size) müssen ebenfalls hoch genug sein.
     */
    'max_upload_kb' => env('SIGNAGE_MAX_UPLOAD_KB', 512000),

    /**
     * Maximale Kantenlänge (px) der Anzeige-Variante von Bildern. Große Originale
     * werden für die Wiedergabe auf diese Größe als WebP heruntergerechnet
     * (schnelleres Laden). 0 = deaktiviert (Original ausliefern).
     */
    'display_max_px' => env('SIGNAGE_DISPLAY_MAX_PX', 1920),

    /**
     * Nicht gekoppelte (pending) Bildschirme nach dieser Zeit per
     * `signage:prune-screens` entfernen (Karteileichen aus dem Register-Endpoint).
     */
    'prune_pending_after_days' => env('SIGNAGE_PRUNE_PENDING_DAYS', 7),

    /**
     * Website-Proxy-Schutz: maximale Antwortgröße (Bytes) und Redirect-Anzahl.
     */
    'proxy_max_bytes' => env('SIGNAGE_PROXY_MAX_BYTES', 5 * 1024 * 1024),
    'proxy_max_redirects' => env('SIGNAGE_PROXY_MAX_REDIRECTS', 4),
];
