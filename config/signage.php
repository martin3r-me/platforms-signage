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
];
