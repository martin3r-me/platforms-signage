<?php

namespace Platform\Signage\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Platform\Signage\Support\ApkUrlInjector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert die Fire-TV-Kiosk-APK aus und injiziert dabei die Player-URL DIESER
 * Instanz (Schema + Host). Dadurch braucht das Gerät keine URL einzutippen und
 * keinen Standort auszuwählen – die richtige Domain steckt bereits in der APK.
 *
 * Zwei Zugänge:
 *  - Eingeloggte Admins laden direkt (authentifizierte Route in routes/web.php).
 *  - Auf dem Fire TV (Downloader-App, kein M365-Login) per kurzem Code-Link
 *    /signage/firetv/{code}.apk. Der 4-stellige Code wird im Dashboard erzeugt
 *    und ist zeitlich begrenzt gültig (siehe CODE_TTL_MINUTES).
 * Die APK ist nur eine Player-Hülle und zeigt erst nach Kopplung Inhalte an.
 */
class FireTvApkController
{
    /** Gültigkeitsdauer eines erzeugten Download-Codes in Minuten. */
    public const CODE_TTL_MINUTES = 30;

    public function download(Request $request, ?string $code = null): Response
    {
        // Zugang nur für eingeloggte Admins ODER mit gültigem, im UI erzeugtem Code.
        $validCode = $code !== null && Cache::get(self::cacheKey($code)) === true;
        abort_unless(
            $validCode || auth()->check(),
            403,
            'Dieser Download-Link ist ungültig oder abgelaufen. Bitte im Dashboard einen neuen Code erzeugen.'
        );

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

    /**
     * Erzeugt einen neuen, zeitlich begrenzt gültigen 4-stelligen Download-Code
     * und legt ihn im Cache ab. Rückgabe: der Code als String (z.B. "0427").
     */
    public static function issueCode(): string
    {
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        Cache::put(self::cacheKey($code), true, now()->addMinutes(self::CODE_TTL_MINUTES));

        return $code;
    }

    private static function cacheKey(string $code): string
    {
        return 'signage:firetv_apk_code:'.$code;
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
