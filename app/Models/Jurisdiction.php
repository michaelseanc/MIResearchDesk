<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jurisdiction extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'type', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Jurisdiction::class, 'parent_id');
    }
}
