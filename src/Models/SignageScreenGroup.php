<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Signage\Models\Concerns\HasUuid;

/**
 * Eine Bildschirm-Gruppe bündelt mehrere Bildschirme eines Teams, um
 * Einstellungen (Standard-Wiedergabeliste/Zeitpläne) gemeinsam zuzuweisen.
 */
class SignageScreenGroup extends Model
{
    use SoftDeletes, HasUuid;

    protected $table = 'signage_screen_groups';

    protected $fillable = ['uuid', 'team_id', 'user_id', 'name', 'description'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function screens(): BelongsToMany
    {
        return $this->belongsToMany(
            SignageScreen::class,
            'signage_screen_group',
            'group_id',
            'screen_id'
        )->withTimestamps();
    }
}
