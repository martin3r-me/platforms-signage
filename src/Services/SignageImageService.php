<?php

namespace Platform\Signage\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Platform\Signage\Models\SignageMedia;

/**
 * Erzeugt eine heruntergerechnete WebP-Anzeige-Variante für große Bilder,
 * damit sie auf TVs schneller laden. Bei Fehlern bleibt es beim Original.
 */
class SignageImageService
{
    public function makeDisplayVariant(SignageMedia $media): void
    {
        $max = (int) config('signage.display_max_px', 1920);

        if ($max <= 0 || $media->kind !== 'image' || !$media->path) {
            return;
        }

        try {
            $disk = $media->disk;
            $content = Storage::disk($disk)->get($media->path);
            if ($content === null) {
                return;
            }

            $manager = new ImageManager(new Driver());
            $img = $manager->read($content);

            // Nur verkleinern, wenn das Original größer als die Zielgröße ist.
            if ($img->width() <= $max && $img->height() <= $max) {
                return;
            }

            $img->scaleDown($max, $max);
            $webp = (string) $img->encode(new WebpEncoder(82));

            $token = Str::random(40);
            $path = $token.'.webp';
            Storage::disk($disk)->put($path, $webp);

            $media->update(['display_path' => $path, 'display_token' => $token]);
        } catch (\Throwable $e) {
            Log::warning('[Signage] Anzeige-Variante fehlgeschlagen', [
                'media_id' => $media->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Liest die Datei eines Mediums und speichert die erkannte Rand-/Hintergrundfarbe
     * in bg_color (für die Letterbox-Farbe im Player). Best-effort – bei Fehlern passiert nichts.
     */
    public function detectAndStoreBackground(SignageMedia $media): void
    {
        if (!$media->path) {
            return;
        }
        try {
            $content = Storage::disk($media->disk)->get($media->path);
            if ($content === null) {
                return;
            }
            $bg = $this->detectBackgroundColor($content);
            if ($bg !== null) {
                $media->update(['bg_color' => $bg]);
            }
        } catch (\Throwable $e) {
            // Randfarbe ist optional – Fehler hier nie durchreichen.
        }
    }

    /**
     * Bestimmt die Rand-/Hintergrundfarbe eines Bildes aus den vier Eckpixeln.
     * Nur wenn die Ecken einheitlich sind (geringe Streuung), wird ein Wert
     * zurückgegeben – sonst null (z.B. randloses Foto), damit nicht falsch geraten wird.
     *
     * @return string|null  Hex ("#rrggbb") oder null
     */
    public function detectBackgroundColor(string $content): ?string
    {
        try {
            $img = (new ImageManager(new Driver()))->read($content);
            $w = $img->width();
            $h = $img->height();
            if ($w < 8 || $h < 8) {
                return null;
            }

            $inset = max(1, (int) floor(min($w, $h) * 0.02)); // 2 % Rand-Abstand (Anti-Aliasing)
            $points = [
                [$inset, $inset],
                [$w - 1 - $inset, $inset],
                [$inset, $h - 1 - $inset],
                [$w - 1 - $inset, $h - 1 - $inset],
            ];

            $rgbs = [];
            foreach ($points as [$x, $y]) {
                $hex = ltrim($img->pickColor($x, $y)->toHex(), '#');
                if (strlen($hex) >= 6) {
                    $rgbs[] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
                }
            }
            if (count($rgbs) < 3) {
                return null;
            }

            // Ecken müssen einheitlich sein, sonst nicht raten.
            for ($ch = 0; $ch < 3; $ch++) {
                $vals = array_column($rgbs, $ch);
                if (max($vals) - min($vals) > 28) {
                    return null;
                }
            }

            $avg = fn (int $ch) => (int) round(array_sum(array_column($rgbs, $ch)) / count($rgbs));

            return sprintf('#%02x%02x%02x', $avg(0), $avg(1), $avg(2));
        } catch (\Throwable $e) {
            return null;
        }
    }
}
