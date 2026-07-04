<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Link extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'entity_id', 'kind', 'platform', 'url', 'title', 'published_at', 'note', 'sensitivity',
    ];

    protected $casts = ['published_at' => 'date'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
