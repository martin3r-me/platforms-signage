<?php

namespace Platform\Signage\Support;

/**
 * Schreibt die Player-URL der aktuellen Instanz in eine fertig signierte APK –
 * ohne Neu-Bau und ohne die Signatur zu brechen. Die URL landet im ZIP-/APK-
 * Kommentar am Dateiende (EOCD), der NICHT Teil der v1-(JAR-)Signatur ist.
 *
 * Die Fire-TV-App liest beim ersten Start ihre eigene APK-Datei und zieht die
 * URL aus diesem Trailer. Format am Dateiende:
 *
 *   [url-bytes][url-länge: 4 Byte LE][MAGIC: 8 Byte "SIGNGURL"]
 *
 * Voraussetzung: die APK ist v1-signiert (enableV2Signing=false), sonst würde
 * die v2-Signatur den geänderten EOCD-Kommentar als manipuliert erkennen.
 */
class ApkUrlInjector
{
    private const MAGIC = 'SIGNGURL';

    /** EOCD-Signatur "PK\x05\x06". */
    private const EOCD_SIGNATURE = "PK\x05\x06";

    public static function inject(string $apk, string $url): string
    {
        $eocd = strrpos($apk, self::EOCD_SIGNATURE);
        if ($eocd === false) {
            throw new \RuntimeException('Kein gültiges ZIP/APK (EOCD nicht gefunden).');
        }

        // Feste EOCD-Struktur ist 22 Byte (Signatur + 16 Byte Felder + 2 Byte Kommentarlänge).
        $fixedEnd = $eocd + 22;
        $base = substr($apk, 0, $fixedEnd);

        $trailer = $url.pack('V', strlen($url)).self::MAGIC;
        $commentLength = strlen($trailer);

        if ($commentLength > 0xFFFF) {
            throw new \RuntimeException('URL zu lang für den APK-Kommentar.');
        }

        // Kommentarlänge (2 Byte LE) an Offset eocd+20 setzen.
        $base = substr_replace($base, pack('v', $commentLength), $eocd + 20, 2);

        return $base.$trailer;
    }

    /** Liest eine zuvor injizierte URL wieder aus (für Tests/Diagnose). */
    public static function extract(string $apk): ?string
    {
        $len = strlen($apk);
        if ($len < 12 || substr($apk, -8) !== self::MAGIC) {
            return null;
        }

        $urlLen = unpack('V', substr($apk, -12, 4))[1];
        if ($urlLen <= 0 || $len < 12 + $urlLen) {
            return null;
        }

        return substr($apk, -12 - $urlLen, $urlLen);
    }
}
