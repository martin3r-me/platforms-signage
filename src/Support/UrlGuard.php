<?php

namespace Platform\Signage\Support;

/**
 * Schutz gegen SSRF: prüft, ob eine URL eine öffentliche http(s)-Adresse ist
 * (kein localhost, keine privaten/reservierten/Link-local-IPs, keine
 * Cloud-Metadaten-Adresse). Genutzt vom Website-Proxy vor jedem Abruf.
 */
class UrlGuard
{
    /** Erlaubt nur http/https mit auflösbarem, öffentlichem Host. */
    public static function isSafePublicHttpUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if ($parts === false) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'] ?? '';
        if ($host === '') {
            return false;
        }

        // IP-Literal direkt prüfen.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        // Hostname auflösen (IPv4); jede aufgelöste IP muss öffentlich sein.
        $ips = @gethostbynamel($host);
        if ($ips === false || $ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /** Öffentliche IP? (schließt private, reservierte, Loopback, Link-local aus) */
    public static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * Löst eine (ggf. relative) Redirect-Location gegen die Basis-URL auf.
     */
    public static function resolveLocation(string $base, string $location): string
    {
        $location = trim($location);

        // Bereits absolut.
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $origin = $scheme.'://'.$host.$port;

        // Protokoll-relativ (//host/pfad).
        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        // Wurzel-relativ (/pfad).
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        // Relativ zum aktuellen Verzeichnis.
        $path = $parts['path'] ?? '/';
        $dir = rtrim(substr($path, 0, strrpos($path, '/') ?: 0), '/');

        return $origin.$dir.'/'.$location;
    }
}
