<?php

namespace Platform\Signage\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Support\UrlGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert eine hinterlegte Website server-seitig aus und entfernt dabei die
 * Header, die das Einbetten im iframe verbieten (X-Frame-Options / CSP
 * frame-ancestors). Dadurch laufen auch nicht-einbettbare Seiten live im
 * Player – inkl. Videos/Animationen, da Unterressourcen direkt von der
 * Ursprungs-Domain geladen werden (per injiziertem <base>).
 *
 * Die Route ist signiert; es wird ausschließlich die gespeicherte URL des
 * Mediums abgerufen (kein offener Proxy -> SSRF-sicher).
 */
class SiteProxyController
{
    public function show(SignageMedia $media): Response
    {
        abort_unless($media->kind === 'website' && $media->stream_url, 404);

        $maxRedirects = (int) config('signage.proxy_max_redirects', 4);
        $maxBytes = (int) config('signage.proxy_max_bytes', 5 * 1024 * 1024);
        $url = $media->stream_url;
        $res = null;

        try {
            // Redirects selbst verfolgen, damit jede Zwischen-URL gegen SSRF
            // geprüft wird (Auto-Redirect könnte sonst auf interne Adressen führen).
            for ($i = 0; $i <= $maxRedirects; $i++) {
                if (!UrlGuard::isSafePublicHttpUrl($url)) {
                    return $this->errorPage('Diese Adresse ist nicht erlaubt.');
                }

                $res = Http::withHeaders([
                        'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                        'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8',
                    ])
                    ->timeout(15)
                    ->withOptions(['allow_redirects' => false])
                    ->get($url);

                $status = $res->status();
                $location = $res->header('Location');
                if ($status >= 300 && $status < 400 && $location) {
                    $url = UrlGuard::resolveLocation($url, $location);
                    continue;
                }

                break;
            }
        } catch (\Throwable $e) {
            return $this->errorPage('Seite nicht erreichbar.');
        }

        if (!$res) {
            return $this->errorPage('Seite nicht erreichbar.');
        }

        // Größen-Deckel: zu große Antworten ablehnen (Worker-Schutz).
        $declaredLength = (int) $res->header('Content-Length');
        if ($declaredLength > $maxBytes) {
            return $this->errorPage('Seite ist zu groß für die Vorschau.');
        }

        $contentType = strtolower((string) $res->header('Content-Type'));

        // Nicht-HTML (z.B. eine direkte Bild-/Datei-URL) unverändert durchreichen.
        if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
            return response(substr($res->body(), 0, $maxBytes), $res->status())
                ->header('Content-Type', $res->header('Content-Type') ?: 'application/octet-stream')
                ->header('Cache-Control', 'public, max-age=300');
        }

        $body = $res->body();
        if (strlen($body) > $maxBytes) {
            $body = substr($body, 0, $maxBytes);
        }

        $base = $this->effectiveUrl($res, $url);
        $html = $this->rewrite($body, $base);

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=utf-8')
            // Bewusst KEIN X-Frame-Options / keine restriktive CSP -> einbettbar.
            ->header('Cache-Control', 'public, max-age=120');
    }

    private function errorPage(string $message): Response
    {
        $safe = htmlspecialchars($message, ENT_QUOTES);

        return response('<!doctype html><meta charset="utf-8"><body style="margin:0;background:#111;color:#bbb;font:14px sans-serif;display:flex;align-items:center;justify-content:center;height:100vh">'.$safe.'</body>', 200)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function effectiveUrl($res, string $fallback): string
    {
        try {
            $uri = $res->effectiveUri();
            if ($uri) {
                return (string) $uri;
            }
        } catch (\Throwable $e) {
            // ignorieren
        }

        return $fallback;
    }

    /**
     * Macht das HTML einbettbar: <base> einsetzen (relative Ressourcen laden von
     * der Ursprungs-Domain) und einbettungs-/CSP-Meta-Tags entfernen.
     */
    private function rewrite(string $html, string $baseUrl): string
    {
        // Vorhandene <base>-Tags entfernen (würden mit unserem kollidieren).
        $html = preg_replace('/<base\b[^>]*>/i', '', $html);

        // CSP / X-Frame-Options aus <meta http-equiv=...> entfernen.
        $html = preg_replace(
            '/<meta\b[^>]*http-equiv\s*=\s*["\']?(content-security-policy|x-frame-options)["\']?[^>]*>/i',
            '',
            $html
        );

        $baseTag = '<base href="'.htmlspecialchars($baseUrl, ENT_QUOTES).'">';

        // Direkt nach dem <head ...> einsetzen; sonst voranstellen.
        if (preg_match('/<head\b[^>]*>/i', $html)) {
            return preg_replace('/(<head\b[^>]*>)/i', '$1'.$baseTag, $html, 1);
        }

        return $baseTag.$html;
    }
}
