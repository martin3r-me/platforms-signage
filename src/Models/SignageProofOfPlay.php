<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Wiedergabe-Nachweis: Medium X lief zu Zeitpunkt Y auf Bildschirm Z.
 */
class SignageProofOfPlay extends Model
{
    protected $table = 'signage_proof_of_plays';

    protected $fillable = ['team_id', 'screen_id', 'media_id', 'played_at', 'seconds'];

    protected $casts = [
        'played_at' => 'datetime',
        'seconds'   => 'integer',
    ];

    public function screen(): BelongsTo
    {
        return $this->belongsTo(SignageScreen::class, 'screen_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(SignageMedia::class, 'media_id');
    }
}
