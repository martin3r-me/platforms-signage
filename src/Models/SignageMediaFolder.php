<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Signage\Models\Concerns\HasUuid;

class SignageMediaFolder extends Model
{
    use SoftDeletes, HasUuid;

    protected $table = 'signage_media_folders';

    protected $fillable = ['uuid', 'team_id', 'user_id', 'name'];

    public function media(): HasMany
    {
        return $this->hasMany(SignageMedia::class, 'folder_id');
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }
}
