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
     * Maximale Upload-Größe in KB (für Bilder/Videos/Audio/Dokumente).
     * Standard 500 MB. Hebt Livewires Standard-Temp-Upload-Limit (12 MB) an.
     * ACHTUNG: PHP (upload_max_filesize/post_max_size) und der Webserver
     * (z.B. nginx client_max_body_size) müssen ebenfalls hoch genug sein.
     */
    'max_upload_kb' => env('SIGNAGE_MAX_UPLOAD_KB', 512000),
];
