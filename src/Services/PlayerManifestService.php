<?php

namespace Platform\Signage\Services;

use Platform\Core\Services\ContextFileService;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignagePlaylist;
use Platform\Signage\Models\SignageScreen;

/**
 * Baut das Wiedergabe-Manifest für einen Bildschirm:
 * - ermittelt die aktuell gültige Visual- und Musik-Playlist (Zeitpläne -> Default)
 * - expandiert Dokument-Items (PDF/PPTX) in einzelne Seiten-Frames
 * - erzeugt signierte/temporäre Medien-URLs
 */
class PlayerManifestService
{
    /**
     * Gültigkeit der signierten Medien-URLs in Minuten (großzügig, da der
     * Player das Manifest dauerhaft nutzt, bis sich content_version ändert).
     */
    private const URL_TTL_MINUTES = 720;

    public function resolve(SignageScreen $screen): array
    {
        $now = now();

        $visualPlaylist = $this->pickPlaylist($screen, $now, 'visual');
        $musicPlaylist  = $this->pickPlaylist($screen, $now, 'music');

        // Musik: Playlist (Zeitplan/Default) ODER ein einzelnes Stream/Audio-Medium.
        if ($musicPlaylist) {
            $music = $this->buildMusicItems($musicPlaylist);
            $musicName = $musicPlaylist->name;
        } elseif ($screen->music_media_id && ($m = SignageMedia::find($screen->music_media_id)) && $m->kind === 'audio') {
            $track = $this->musicTrack($m);
            $music = $track ? [$track] : [];
            $musicName = $m->name;
        } else {
            $music = [];
            $musicName = null;
        }

        return [
            'screen' => [
                'uuid'        => $screen->uuid,
                'name'        => $screen->name,
                'orientation' => $screen->orientation,
            ],
            'content_version'   => (int) $screen->content_version,
            'poll_interval'     => (int) config('signage.poll_interval_seconds', 10),
            'items'             => $visualPlaylist ? $this->buildVisualItems($visualPlaylist) : [],
            'music'             => $music,
            'visual_playlist'   => $visualPlaylist?->name,
            'music_playlist'    => $musicName,
        ];
    }

    /**
     * Wählt die Playlist der gegebenen Art (visual/music):
     * passender Zeitplan (höchste Priorität) -> sonst Standard-Playlist des Screens.
     */
    private function pickPlaylist(SignageScreen $screen, \DateTimeInterface $now, string $kind): ?SignagePlaylist
    {
        $schedules = $screen->schedules()
            ->where('active', true)
            ->orderByDesc('priority')
            ->get();

        foreach ($schedules as $schedule) {
            if (!$schedule->matchesNow($now)) {
                continue;
            }

            $playlistId = $kind === 'music' ? $schedule->music_playlist_id : $schedule->playlist_id;
            if ($playlistId) {
                $playlist = SignagePlaylist::with('items.media')->find($playlistId);
                if ($playlist && $playlist->kind === $kind) {
                    return $playlist;
                }
            }
        }

        $defaultId = $kind === 'music' ? $screen->music_playlist_id : $screen->default_playlist_id;
        if ($defaultId) {
            return SignagePlaylist::with('items.media')->find($defaultId);
        }

        return null;
    }

    private function buildVisualItems(SignagePlaylist $playlist): array
    {
        $defaultDuration = (int) config('signage.default_image_duration', 10);
        $frames = [];

        foreach ($playlist->items as $item) {
            $media = $item->media;
            if (!$media) {
                continue;
            }

            $duration = $item->duration_seconds ?: $defaultDuration;

            if ($media->kind === 'app') {
                $frames[] = [
                    'type'       => 'app',
                    'app_type'   => $media->app_type,
                    'config'     => $media->config ?? [],
                    'duration'   => $duration,
                    'transition' => $item->transition,
                ];
            } elseif ($media->kind === 'image') {
                $frames[] = [
                    'type'       => 'image',
                    'url'        => $this->mediaUrl($media),
                    'duration'   => $duration,
                    'transition' => $item->transition,
                ];
            } elseif ($media->kind === 'video') {
                $frames[] = [
                    'type'       => 'video',
                    'url'        => $this->mediaUrl($media),
                    'transition' => $item->transition,
                ];
            } elseif ($media->kind === 'document') {
                // Nur fertig konvertierte Dokumente einblenden.
                if (!$media->isReady()) {
                    continue;
                }
                foreach ($media->pages as $page) {
                    $frames[] = [
                        'type'       => 'image',
                        'url'        => $this->pageUrl($page),
                        'duration'   => $duration,
                        'transition' => $item->transition,
                    ];
                }
            }
            // Audio in einer Visual-Playlist wird ignoriert.
        }

        return $frames;
    }

    private function buildMusicItems(SignagePlaylist $playlist): array
    {
        $tracks = [];

        foreach ($playlist->items as $item) {
            $track = $this->musicTrack($item->media);
            if ($track) {
                $tracks[] = $track;
            }
        }

        return $tracks;
    }

    /**
     * Baut einen Musik-Track aus einem Audio-Medium (Datei, direkter Stream oder Embed).
     */
    private function musicTrack(?SignageMedia $media): ?array
    {
        if (!$media || $media->kind !== 'audio') {
            return null;
        }

        if ($media->isStream()) {
            return [
                'type'  => $media->is_embed ? 'embed' : 'stream',
                'url'   => $media->stream_url,
                'title' => $media->name,
            ];
        }

        return [
            'type'  => 'file',
            'url'   => $this->mediaUrl($media),
            'title' => $media->name,
        ];
    }

    private function mediaUrl(SignageMedia $media): string
    {
        return ContextFileService::generateUrl(
            $media->disk,
            $media->path,
            $media->token,
            'signage.media.show',
            self::URL_TTL_MINUTES
        );
    }

    private function pageUrl($page): string
    {
        return ContextFileService::generateUrl(
            $page->disk,
            $page->path,
            $page->token,
            'signage.media.show',
            self::URL_TTL_MINUTES
        );
    }
}
