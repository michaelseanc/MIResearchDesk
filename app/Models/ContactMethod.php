<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMethod extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['entity_id', 'method', 'value', 'is_preferred', 'restrictions', 'sensitivity'];

    protected $casts = ['is_preferred' => 'boolean'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
