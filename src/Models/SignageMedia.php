<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Signage\Models\Concerns\HasUuid;

class SignageMedia extends Model
{
    use SoftDeletes, HasUuid;

    protected $table = 'signage_media';

    protected $fillable = [
        'uuid', 'team_id', 'folder_id', 'user_id', 'name', 'kind', 'app_type', 'config',
        'source_type', 'stream_url', 'is_embed',
        'disk', 'path', 'token', 'display_path', 'display_token', 'original_name', 'mime_type', 'file_size',
        'width', 'height', 'duration_seconds', 'processing_status', 'page_count',
    ];

    protected $casts = [
        'config' => 'array',
        'is_embed' => 'boolean',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_seconds' => 'integer',
        'page_count' => 'integer',
    ];

    protected static function booted(): void
    {
        // Beim Löschen die zugehörigen Storage-Dateien entfernen (Original,
        // Anzeige-Variante, Dokument-Seiten) – sonst wächst der Speicher.
        static::deleting(function (self $media) {
            $media->deleteStorageFiles();
        });
    }

    /** Entfernt alle eigenen Dateien dieses Mediums vom Storage. */
    public function deleteStorageFiles(): void
    {
        $defaultDisk = $this->disk ?: config('filesystems.default', 'public');
        $storage = \Illuminate\Support\Facades\Storage::disk($defaultDisk);

        foreach (array_filter([$this->path, $this->display_path]) as $path) {
            if ($storage->exists($path)) {
                $storage->delete($path);
            }
        }

        // Dokument-Seiten: Dateien + Zeilen entfernen.
        foreach ($this->pages()->get() as $page) {
            $pageDisk = \Illuminate\Support\Facades\Storage::disk($page->disk ?: $defaultDisk);
            if ($page->path && $pageDisk->exists($page->path)) {
                $pageDisk->delete($page->path);
            }
            $page->delete();
        }
    }

    public function isStream(): bool
    {
        return $this->source_type === 'stream';
    }

    public function isApp(): bool
    {
        return $this->kind === 'app';
    }

    public function isWebsite(): bool
    {
        return $this->kind === 'website';
    }

    /** Deutsche Bezeichnung des Medientyps (für die UI). */
    public function kindLabel(): string
    {
        return match ($this->kind) {
            'image'    => 'Bild',
            'video'    => 'Video',
            'audio'    => 'Audio',
            'document' => 'Dokument',
            'app'      => 'App',
            'website'  => 'Website',
            'stream'   => 'Stream',
            default    => ucfirst((string) $this->kind),
        };
    }

    /** Deutsche Bezeichnung des App-Typs (Uhr/Wetter). */
    public function appTypeLabel(): string
    {
        return match ($this->app_type) {
            'clock'   => 'Uhr',
            'weather' => 'Wetter',
            default   => (string) $this->app_type,
        };
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SignageMediaPage::class, 'media_id')->orderBy('page_number');
    }

    /** Erste Dokumentseite – eager-loadbar (Vorschau ohne N+1). */
    public function firstPage(): HasOne
    {
        return $this->hasOne(SignageMediaPage::class, 'media_id')->oldestOfMany('page_number');
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function folder()
    {
        return $this->belongsTo(SignageMediaFolder::class, 'folder_id');
    }

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_id');
    }

    public function isDocument(): bool
    {
        return $this->kind === 'document';
    }

    public function isReady(): bool
    {
        return $this->processing_status === 'ready';
    }

    /**
     * Vorschau-URL für die Galerie: Bild direkt, Dokument = erste Seite,
     * Video/Audio liefern null (UI zeigt ein Icon).
     */
    public function previewUrl(): ?string
    {
        $gen = fn (string $disk, string $path, string $token) => \Platform\Core\Services\ContextFileService::generateUrl(
            $disk, $path, $token, 'signage.media.show', 120
        );

        // Anzeige-Variante zuerst: Bild-Downscale ODER Website-Screenshot.
        if ($this->display_token && $this->display_path) {
            return $gen($this->disk, $this->display_path, $this->display_token);
        }

        if ($this->kind === 'image' && $this->token && $this->path) {
            return $gen($this->disk, $this->path, $this->token);
        }

        if ($this->kind === 'document') {
            // Nutzt die eager-geladene firstPage-Relation, sonst genau eine Query.
            $page = $this->firstPage;

            return $page ? $gen($page->disk, $page->path, $page->token) : null;
        }

        return null;
    }
}
