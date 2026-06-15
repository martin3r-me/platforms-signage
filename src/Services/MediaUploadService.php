<?php

namespace Platform\Signage\Services;

use Illuminate\Http\UploadedFile;
use Platform\Core\Services\ContextFileService;
use Platform\Signage\Jobs\ConvertDocumentJob;
use Platform\Signage\Models\SignageMedia;

/**
 * Legt aus einer hochgeladenen Datei ein SignageMedia an (inkl. Speicherung,
 * Bild-Variante bzw. Dokument-Konvertierung). Gemeinsam genutzt vom Medien-
 * Upload und vom Direkt-Upload in der Wiedergabeliste – damit ein Medium überall
 * identisch entsteht und in der Medienbibliothek auftaucht.
 */
class MediaUploadService
{
    public function __construct(private ContextFileService $files)
    {
    }

    public function store(UploadedFile $file, int $teamId, ?int $userId, ?int $folderId = null): SignageMedia
    {
        $kind = $this->determineKind($file);

        $media = SignageMedia::create([
            'team_id'           => $teamId,
            'folder_id'         => $folderId,
            'user_id'           => $userId,
            'name'              => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'kind'              => $kind,
            'disk'              => config('filesystems.default', 'public'),
            'path'              => '',
            'token'             => '',
            'original_name'     => $file->getClientOriginalName(),
            'processing_status' => $kind === 'document' ? 'pending' : 'ready',
        ]);

        $result = $this->files->uploadForContext($file, 'signage_media', $media->id, [
            'team_id'           => $teamId,
            'user_id'           => $userId,
            'keep_original'     => true,
            'generate_variants' => $kind === 'image',
        ]);

        $media->update([
            'disk'      => config('filesystems.default', 'public'),
            'path'      => $result['path'],
            'token'     => $result['token'],
            'mime_type' => $result['mime_type'] ?? $file->getMimeType(),
            'file_size' => $result['file_size'] ?? $file->getSize(),
            'width'     => $result['width'] ?? null,
            'height'    => $result['height'] ?? null,
        ]);

        if ($kind === 'document') {
            ConvertDocumentJob::dispatch($media->id);
        } elseif ($kind === 'image') {
            // Heruntergerechnete Anzeige-Variante für schnelleres Laden auf TVs.
            app(SignageImageService::class)->makeDisplayVariant($media->refresh());
        }

        return $media;
    }

    public function determineKind(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if ($ext === 'pdf' || in_array($ext, ['ppt', 'pptx'], true)) {
            return 'document';
        }

        return match ($ext) {
            'jpg', 'jpeg', 'png', 'webp', 'gif' => 'image',
            'mp4', 'webm' => 'video',
            'mp3', 'aac', 'ogg', 'wav' => 'audio',
            default => 'document',
        };
    }
}
