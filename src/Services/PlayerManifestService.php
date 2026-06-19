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
            'items'             => $visualPlaylist ? $this->buildVisualItems($visualPlaylist, $screen) : [],
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
     * Wann schaltet die Auswahl (visual/music) das nächste Mal um? Prüft die
     * Intervall-Grenzen aller zugewiesenen Regeln (heute + morgen) und liefert die
     * erste Grenze, an der sich die aufgelöste Liste/Quelle ändert.
     *
     * @return array{at:string, schedule:?string, playlist:?string, source:string}|null
     */
    public function nextChange(SignageScreen $screen, \DateTimeInterface $now, string $kind): ?array
    {
        $current = $this->pickSelection($screen, $now, $kind);
        $currentName = $current['playlist']?->name;

        $schedules = $screen->schedules()
            ->with(['rules' => fn ($q) => $q->where('active', true)])
            ->get();

        $dowToday = (int) $now->format('N');
        $curMin = (int) $now->format('G') * 60 + (int) $now->format('i');

        // Kandidaten = Minuten-Abstand bis zur nächsten Intervall-Grenze (heute + morgen).
        $deltas = [];
        foreach ([0, 1] as $offset) {
            $dow = ($dowToday - 1 + $offset) % 7 + 1;
            foreach ($schedules as $schedule) {
                foreach ($schedule->rules as $rule) {
                    $playlistId = $kind === 'music' ? $rule->music_playlist_id : $rule->playlist_id;
                    if (!$playlistId) {
                        continue;
                    }
                    foreach ($rule->dayIntervals() as $iv) {
                        if ($iv['day'] !== $dow) {
                            continue;
                        }
                        foreach ([$iv['start'], $iv['end']] as $boundary) {
                            $delta = $offset * 1440 + $boundary - $curMin;
                            if ($delta > 0 && $delta <= 1440) {
                                $deltas[$delta] = true;
                            }
                        }
                    }
                }
            }
        }

        $deltas = array_keys($deltas);
        sort($deltas);

        $base = \Illuminate\Support\Carbon::instance($now);
        foreach ($deltas as $delta) {
            $candidate = $base->copy()->addMinutes($delta);
            $sel = $this->pickSelection($screen, $candidate, $kind);
            if (($sel['playlist']?->name) !== $currentName || $sel['source'] !== $current['source']) {
                return [
                    'at'       => $candidate->format('H:i'),
                    'schedule' => $sel['schedule']?->name,
                    'playlist' => $sel['playlist']?->name,
                    'source'   => $sel['source'],
                ];
            }
        }

        return null;
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
            // Nächste Umschaltung (Anzeige): wann + was kommt danach (oder null).
            'next'   => $this->nextChange($screen, $now, 'visual'),
        ];
    }

    private function buildVisualItems(SignagePlaylist $playlist, SignageScreen $screen): array
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
                $config = $media->config ?? [];
                // Dynamische Apps bekommen ihre Daten-Endpoint-URL (Geräte-Token) injiziert.
                if ($media->app_type === 'events') {
                    $config['endpoint'] = route('signage.api.screen.events', ['deviceToken' => $screen->device_token]);
                }
                $frames[] = [
                    'type'       => 'app',
                    'app_type'   => $media->app_type,
                    'config'     => $config,
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
