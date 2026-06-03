<?php

namespace Platform\Signage\Livewire\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * Ermittelt die abzuspielende Stream-URL + ob sie als iframe eingebettet werden muss.
 * TuneIn-Links werden über die TuneIn-Tune-API in einen direkten Audio-Stream
 * aufgelöst (spielt zuverlässig per <audio>, statt als nicht-autostartender iframe).
 */
trait ResolvesStream
{
    /**
     * @return array{0:string,1:bool} [url, isEmbed]
     */
    protected function resolveStream(string $url, string $type): array
    {
        if (preg_match('#tunein\.com/.*?\b(s\d+)\b#i', $url, $m) || preg_match('#/player/(s\d+)#i', $url, $m)) {
            $direct = $this->resolveTuneIn($m[1]);
            if ($direct) {
                return [$direct, false];
            }
        }

        $isEmbed = $type === 'embed' || str_contains($url, '/embed');

        return [$url, $isEmbed];
    }

    protected function resolveTuneIn(string $stationId): ?string
    {
        try {
            $res = Http::timeout(8)->get('https://opml.radiotime.com/Tune.ashx', [
                'id'     => $stationId,
                'render' => 'json',
            ]);
            $body = $res->json('body');
            if (!is_array($body)) {
                return null;
            }

            // Kandidaten ranken: https + mp3/aac ist auf einer HTTPS-Seite am zuverlässigsten.
            $candidates = [];
            foreach ($body as $entry) {
                if (empty($entry['url'])) {
                    continue;
                }
                $url = $entry['url'];
                $mt = strtolower($entry['media_type'] ?? '');
                $isHttps = str_starts_with($url, 'https://');
                $isDirect = in_array($mt, ['mp3', 'aac', 'aacp', 'ogg'], true);
                $candidates[] = ['url' => $url, 'score' => ($isHttps ? 2 : 0) + ($isDirect ? 1 : 0)];
            }

            if (empty($candidates)) {
                return null;
            }

            usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

            return $candidates[0]['url'];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
