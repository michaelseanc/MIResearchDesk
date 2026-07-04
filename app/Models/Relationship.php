<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

/**
 * A structured, dated, reviewable newsroom assertion between two entities — not a bare graph line.
 * Evidence-first rule enforced here: a relationship cannot transition to `verified` without at
 * least one citation in relationship_evidence.
 */
class Relationship extends Model
{
    use BelongsToOrganization, HasUuid, TracksAuthor, SoftDeletes;

    public const VERIFIED = 'verified';

    protected $fillable = [
        'from_entity_id', 'to_entity_id', 'relationship_type_id', 'is_directional',
        'start_date', 'end_date', 'status', 'verification_state', 'confidence',
        'issue_tag_id', 'notes', 'sensitivity', 'last_reviewed_at', 'last_reviewed_by',
    ];

    protected $casts = [
        'is_directional' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'confidence' => 'integer',
        'last_reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Evidence-first guard: a relationship may only be "verified" while it has supporting
        // evidence. Covers both creation (no evidence can exist yet) and the transition on update.
        static::saving(function (Relationship $rel): void {
            if ($rel->verification_state !== self::VERIFIED) {
                return;
            }

            $wasAlreadyVerified = $rel->exists && $rel->getOriginal('verification_state') === self::VERIFIED;

            if (! $wasAlreadyVerified && $rel->evidence()->count() === 0) {
                throw new RuntimeException('A relationship cannot be marked "verified" without at least one piece of evidence.');
            }
        });
    }

    public function scopeNotSealed(Builder $q): Builder
    {
        return $q->where('sensitivity', '!=', 'sealed');
    }

    public function fromEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'from_entity_id');
    }

    public function toEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'to_entity_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(RelationshipType::class, 'relationship_type_id');
    }

    public function issueTag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'issue_tag_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(RelationshipEvidence::class);
    }

    public function citations(): BelongsToMany
    {
        return $this->belongsToMany(DocumentCitation::class, 'relationship_evidence')
            ->withPivot('note')->withTimestamps();
    }
}
