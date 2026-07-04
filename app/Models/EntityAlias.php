<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityAlias extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['entity_id', 'alias', 'alias_type'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
