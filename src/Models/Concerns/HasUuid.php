<?php

namespace Platform\Signage\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Vergibt beim Anlegen automatisch eine eindeutige UUID auf der `uuid`-Spalte.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = (string) Str::uuid();
                } while (static::where('uuid', $uuid)->exists());

                $model->uuid = $uuid;
            }
        });
    }
}
