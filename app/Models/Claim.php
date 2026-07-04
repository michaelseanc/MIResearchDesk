<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Claim extends Model
{
    use BelongsToOrganization, TracksAuthor, SoftDeletes;

    protected $fillable = [
        'subject_entity_id', 'statement', 'verification_state', 'sensitivity',
        'review_flag', 'review_due_at',
    ];

    protected $casts = ['review_due_at' => 'datetime'];

    public function scopeNotSealed(Builder $q): Builder
    {
        return $q->where('sensitivity', '!=', 'sealed');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'subject_entity_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ClaimEvidence::class);
    }
}
