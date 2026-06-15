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
        if ($screen->timezone) {
            try {
                $now = $now->copy()->setTimezone($screen->timezone);
            } catch (\Throwable $e) {
                // ungültige Zeitzone -> App-Standard behalten
            }
        }

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

    private function pickPlaylist(SignageScreen $screen, \DateTimeInterface $now, string $kind): ?SignagePlaylist
    {
        return $this->pickSelection($screen, $now, $kind)['playlist'];
    }

    /**
     * Wählt die Playlist der gegebenen Art (visual/music) und gibt zusätzlich den
     * gewinnenden Zeitplan sowie die Quelle zurück:
     * passende, aktive Regel aus ALLEN zugewiesenen Zeitplänen (höchste Priorität
     * über alle Pläne hinweg) -> sonst Standard-Playlist des Screens -> sonst nichts.
     *
     * @return array{playlist:?SignagePlaylist, schedule:?\Platform\Signage\Models\SignageSchedule, source:string}
     */
    private function pickSelection(SignageScreen $screen, \DateTimeInterface $now, string $kind): array
    {
        $schedules = $screen->schedules()
            ->with(['rules' => fn ($q) => $q->where('active', true)])
            ->get();

        // (Regel, Plan)-Paare über alle Pläne hinweg nach Priorität sortieren.
        $pairs = $schedules
            ->flatMap(fn ($schedule) => $schedule->rules->map(fn ($rule) => ['rule' => $rule, 'schedule' => $schedule]))
            ->sortByDesc(fn ($pair) => $pair['rule']->priority)
            ->values();

        foreach ($pairs as $pair) {
            $rule = $pair['rule'];
            if (!$rule->matchesNow($now)) {
                continue;
            }

            $playlistId = $kind === 'music' ? $rule->music_playlist_id : $rule->playlist_id;
            if ($playlistId) {
                $playlist = SignagePlaylist::with('items.media')->find($playlistId);
                if ($playlist && $playlist->kind === $kind) {
                    return ['playlist' => $playlist, 'schedule' => $pair['schedule'], 'source' => 'schedule'];
                }
            }
        }

        $defaultId = $kind === 'music' ? $screen->music_playlist_id : $screen->default_playlist_id;
        if ($defaultId && ($playlist = SignagePlaylist::with('items.media')->find($defaultId))) {
            return ['playlist' => $playlist, 'schedule' => null, 'source' => 'default'];
        }

        return ['playlist' => null, 'schedule' => null, 'source' => 'none'];
    }

    /**
     * Was läuft gerade? Liefert den aktiven Zeitplan + die Liste (Anzeige & Musik)
     * für die aktuelle Bildschirm-Zeit – nutzt dieselbe Auflösung wie der Player.
     */
    public function activeSelection(SignageScreen $screen): array
    {
        $now = now();
        $tz = $screen->timezone;
        if ($tz) {
            try {
                $now = $now->copy()->setTimezone($tz);
            } catch (\Throwable $e) {
                $tz = null; // ungültige Zeitzone -> App-Standard
            }
        }

        $visual = $this->pickSelection($screen, $now, 'visual');
        $music = $this->pickSelection($screen, $now, 'music');

        // Musik kann auch ein einzelnes Medium (Stream/Audio) als Standard sein.
        $musicName = $music['playlist']?->name;
        if ($music['source'] === 'none' && $screen->music_media_id
            && ($m = SignageMedia::find($screen->music_media_id)) && $m->kind === 'audio') {
            $music['source'] = 'default';
            $musicName = $m->name;
        }

        return [
            'now'    => $now->format('H:i'),
            'tz'     => $tz ?: (string) config('app.timezone'),
            'visual' => [
                'source'   => $visual['source'],
                'schedule' => $visual['schedule']?->name,
                'playlist' => $visual['playlist']?->name,
            ],
            'music'  => [
                'source'   => $music['source'],
                'schedule' => $music['schedule']?->name,
                'playlist' => $musicName,
            ],
        ];
    }

    private function buildVisualItems(SignagePlaylist $playlist): array
    {
        $defaultDuration = (int) config('signage.default_image_duration', 10);
        $fit = $playlist->fit === 'cover' ? 'cover' : 'contain';
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
                    'fit'        => $fit,
                ];
            } elseif ($media->kind === 'video') {
                $frames[] = [
                    'type'       => 'video',
                    'url'        => $this->mediaUrl($media),
                    'transition' => $item->transition,
                    'fit'        => $fit,
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
                        'fit'        => $fit,
                    ];
                }
            } elseif ($media->kind === 'website') {
                $proxied = ($media->config['render_mode'] ?? 'iframe') === 'proxy';
                $frames[] = [
                    'type'       => 'website',
                    // Proxy-Modus: über den Server ausliefern (entfernt X-Frame-Options/CSP),
                    // damit auch nicht-einbettbare Seiten live (inkl. Video) laufen.
                    'url'        => $proxied ? $this->proxyUrl($media) : $media->stream_url,
                    'proxied'    => $proxied,
                    'duration'   => $duration,
                    'transition' => $item->transition,
                ];
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
        // Bilder: bevorzugt die heruntergerechnete Anzeige-Variante (schnelleres Laden).
        $path = $media->display_path ?: $media->path;
        $token = $media->display_token ?: $media->token;

        return ContextFileService::generateUrl(
            $media->disk,
            $path,
            $token,
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

    /**
     * Signierte Proxy-URL für eine Website (Server liefert die Seite aus und
     * entfernt einbettungs-blockierende Header). Nutzt die hinterlegte URL des
     * Mediums – kein offener Proxy (SSRF-sicher).
     */
    private function proxyUrl(SignageMedia $media): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'signage.site.proxy',
            now()->addMinutes(self::URL_TTL_MINUTES),
            ['media' => $media->id]
        );
    }
}
