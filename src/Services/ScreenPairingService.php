<?php

namespace Platform\Signage\Services;

use Illuminate\Support\Str;
use Platform\Signage\Models\SignageScreen;

/**
 * Kopplung von Bildschirmen: Registrierung (Player) und Übernahme (Admin via Code).
 */
class ScreenPairingService
{
    /**
     * Registriert ein neues Gerät (vom Player aufgerufen).
     * Erzeugt einen pending-Screen mit geheimem device_token und einem
     * menschenlesbaren Pairing-Code.
     */
    public function register(): SignageScreen
    {
        return SignageScreen::create([
            'device_token' => $this->uniqueDeviceToken(),
            'pairing_code' => $this->uniquePairingCode(),
            'status'       => 'pending',
        ]);
    }

    /**
     * Übernimmt einen pending-Screen anhand seines Pairing-Codes in ein Team.
     *
     * @throws \RuntimeException wenn kein passender pending-Screen existiert.
     */
    public function claim(string $code, int $teamId, ?string $name = null, ?int $userId = null): SignageScreen
    {
        $code = strtoupper(trim($code));

        $screen = SignageScreen::whereNull('team_id')
            ->where('status', 'pending')
            ->where('pairing_code', $code)
            ->first();

        if (!$screen) {
            throw new \RuntimeException('Kein Bildschirm mit diesem Kopplungs-Code gefunden.');
        }

        $screen->update([
            'team_id'      => $teamId,
            'name'         => $name ?: 'Bildschirm '.Str::upper(Str::random(4)),
            'status'       => 'active',
            'pairing_code' => null,
            'paired_at'    => now(),
            'content_version' => $screen->content_version + 1,
        ]);

        return $screen->refresh();
    }

    /**
     * Erhöht die content_version -> Player lädt beim nächsten Poll neu.
     */
    public function bumpVersion(SignageScreen $screen): void
    {
        $screen->increment('content_version');
    }

    private function uniqueDeviceToken(): string
    {
        do {
            $token = Str::random(48);
        } while (SignageScreen::where('device_token', $token)->exists());

        return $token;
    }

    /**
     * 6-stelliger Code ohne leicht verwechselbare Zeichen (0/O, 1/I).
     */
    private function uniquePairingCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (SignageScreen::where('pairing_code', $code)->exists());

        return $code;
    }
}
