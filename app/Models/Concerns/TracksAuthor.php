<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Stamps created_by / updated_by from the authenticated user for editorial accountability.
 * Silently no-ops when there is no authenticated user (seeders, imports).
 */
trait TracksAuthor
{
    public static function bootTracksAuthor(): void
    {
        static::creating(function (Model $model): void {
            if (($id = auth()->id()) !== null) {
                $model->created_by ??= $id;
                $model->updated_by ??= $id;
            }
        });

        static::updating(function (Model $model): void {
            if (($id = auth()->id()) !== null) {
                $model->updated_by = $id;
            }
        });
    }
}
