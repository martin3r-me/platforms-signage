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
}
