<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A story (one article) or an issue (a years-long beat). The reporting workspace: links entities,
 * claims, contacts, documents, tasks, and records requests around a central question.
 */
class Story extends Model
{
    use BelongsToOrganization, HasUuid, TracksAuthor, SoftDeletes;

    protected $fillable = [
        'title', 'type', 'status', 'priority', 'central_question', 'why_it_matters',
        'open_questions', 'known_facts', 'counterarguments', 'next_action',
    ];

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'story_entities')
            ->withPivot('role_note')
            ->withPivotValue('organization_id', Organization::currentId())
            ->withTimestamps();
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'story_contacts')
            ->withPivot('attribution_terms')
            ->withPivotValue('organization_id', Organization::currentId())
            ->withTimestamps();
    }

    public function claims(): BelongsToMany
    {
        return $this->belongsToMany(Claim::class, 'story_claims')
            ->withPivotValue('organization_id', Organization::currentId())
            ->withTimestamps();
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_story_links')
            ->withPivotValue('organization_id', Organization::currentId())
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(StoryTask::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ContactInteraction::class);
    }

    public function recordsRequests(): HasMany
    {
        return $this->hasMany(PublicRecordsRequest::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->withPivotValue('organization_id', Organization::currentId())
            ->withTimestamps();
    }
}
