<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Signage\Models\Concerns\HasUuid;

/**
 * Ein wiederverwendbarer Zeitplan (Plan) mit Regeln. Wird einem Bildschirm zugewiesen.
 */
class SignageSchedule extends Model
{
    use SoftDeletes, HasUuid;

    protected $table = 'signage_schedules';

    protected $fillable = ['uuid', 'team_id', 'name'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(SignageScheduleRule::class, 'schedule_id')->orderByDesc('priority');
    }
}
