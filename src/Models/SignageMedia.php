<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Signage\Models\Concerns\HasUuid;

class SignageMedia extends Model
{
    use SoftDeletes, HasUuid;

    protected $table = 'signage_media';

    protected $fillable = [
        'uuid', 'team_id', 'folder_id', 'user_id', 'name', 'kind', 'app_type', 'config',
        'source_type', 'stream_url', 'is_embed',
        'disk', 'path', 'token', 'original_name', 'mime_type', 'file_size',
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

    public function isStream(): bool
    {
        return $this->source_type === 'stream';
    }

    public function isApp(): bool
    {
        return $this->kind === 'app';
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SignageMediaPage::class, 'media_id')->orderBy('page_number');
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
        if (empty($this->token) || empty($this->path)) {
            return null;
        }

        $gen = fn (string $disk, string $path, string $token) => \Platform\Core\Services\ContextFileService::generateUrl(
            $disk, $path, $token, 'signage.media.show', 120
        );

        if ($this->kind === 'image') {
            return $gen($this->disk, $this->path, $this->token);
        }

        if ($this->kind === 'document') {
            $page = $this->pages()->orderBy('page_number')->first();

            return $page ? $gen($page->disk, $page->path, $page->token) : null;
        }

        return null;
    }
}
