<?php

namespace Platform\Signage\Http\Controllers;

use Platform\Signage\Support\ApkUrlInjector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert die Fire-TV-Kiosk-APK aus und injiziert dabei die Player-URL DIESER
 * Instanz (Schema + Host). Dadurch braucht das Gerät keine URL einzutippen und
 * keinen Standort auszuwählen – die richtige Domain steckt bereits in der APK.
 *
 * Liegt hinter der authentifizierten Modul-Route: nur berechtigte Nutzer der
 * jeweiligen Instanz/Standort kommen an den Download (= bestehende Team-Rechte).
 */
class FireTvApkController
{
    public function download(): Response
    {
        $path = self::apkPath();
        abort_unless($path !== null && is_file($path), 404, 'Die Fire-TV-App ist auf diesem Server noch nicht hinterlegt.');

        $apk = file_get_contents($path);
        $playerUrl = url('/signage/play');
        $out = ApkUrlInjector::inject($apk, $playerUrl);

        return response($out, 200, [
            'Content-Type'        => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="signage-player.apk"',
            'Content-Length'      => (string) strlen($out),
            'Cache-Control'       => 'no-store',
        ]);
    }

    /** Ist eine APK hinterlegt (steuert die Sichtbarkeit des Buttons)? */
    public static function available(): bool
    {
        $path = self::apkPath();

        return $path !== null && is_file($path);
    }

    private static function apkPath(): ?string
    {
        $configured = config('signage.firetv_apk_path');
        if ($configured) {
            return $configured;
        }

        // Standard: im Modul abgelegte, vorab gebaute APK.
        return dirname(__DIR__, 3).'/resources/firetv/signage-player.apk';
    }
}
