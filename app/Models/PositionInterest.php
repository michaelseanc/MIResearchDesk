<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A structured position/interest record. record_type carries the epistemic category
 * (public_position vs financial_interest vs stated_motivation vs editorial_analysis ...), so a
 * documented fact and a reported motive are never flattened into the same "fact".
 */
class PositionInterest extends Model
{
    use BelongsToOrganization, TracksAuthor, SoftDeletes;

    protected $table = 'positions_interests';

    protected $fillable = [
        'entity_id', 'topic_tag_id', 'record_type', 'summary', 'date_start', 'date_end',
        'verification_status', 'visibility', 'review_flag',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_end' => 'date',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function topicTag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'topic_tag_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(PositionEvidence::class, 'position_id');
    }
}
