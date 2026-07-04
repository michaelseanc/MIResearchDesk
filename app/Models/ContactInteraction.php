<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactInteraction extends Model
{
    use BelongsToOrganization, TracksAuthor, SoftDeletes;

    protected $fillable = [
        'entity_id', 'story_id', 'interaction_type', 'occurred_at', 'summary',
        'attribution_terms', 'follow_up_at', 'visibility',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'follow_up_at' => 'datetime',
    ];

    /** Sealed interactions never appear in ordinary lists/search. */
    public function scopeNotSealed(Builder $q): Builder
    {
        return $q->where('visibility', '!=', 'sealed');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
