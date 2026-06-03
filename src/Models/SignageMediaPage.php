<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignageMediaPage extends Model
{
    protected $table = 'signage_media_pages';

    protected $fillable = [
        'media_id', 'page_number', 'disk', 'path', 'token', 'width', 'height',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(SignageMedia::class, 'media_id');
    }

    public function previewUrl(): ?string
    {
        if (!$this->path || !$this->token) {
            return null;
        }

        return \Platform\Core\Services\ContextFileService::generateUrl(
            $this->disk, $this->path, $this->token, 'signage.media.show', 120
        );
    }
}
