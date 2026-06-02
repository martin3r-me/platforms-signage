<?php

namespace Platform\Signage\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageMediaPage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liefert eine Mediendatei (oder Dokument-Seite) anhand ihres Tokens aus.
 * Die Route ist signiert ('signed'-Middleware) – nur das Manifest erzeugt gültige URLs.
 *
 * Wird nur für lokale Disks genutzt; bei Cloud-Storage liefert das Manifest
 * direkte (presigned) URLs und diese Route wird nicht angefragt.
 */
class MediaController
{
    public function show(string $token): Response
    {
        [$disk, $path] = $this->resolve($token);

        $storage = Storage::disk($disk);

        if (!$storage->exists($path)) {
            abort(404);
        }

        // Cloud-Disk mit Temp-URLs: dorthin weiterleiten.
        if ($storage->providesTemporaryUrls()) {
            return redirect()->away($storage->temporaryUrl($path, now()->addMinutes(30)));
        }

        return new StreamedResponse(function () use ($storage, $path) {
            $stream = $storage->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'  => $storage->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @return array{0:string,1:string} [disk, path]
     */
    private function resolve(string $token): array
    {
        if ($media = SignageMedia::where('token', $token)->first()) {
            return [$media->disk, $media->path];
        }

        if ($page = SignageMediaPage::where('token', $token)->first()) {
            return [$page->disk, $page->path];
        }

        abort(404);
    }
}
