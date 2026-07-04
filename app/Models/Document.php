<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A first-class research object. Files live on the PRIVATE disk (never public URLs). Citations
 * are the atomic evidence unit other records point to; file_hash guards against duplicate or
 * confused versions.
 */
class Document extends Model
{
    use BelongsToOrganization, HasUuid, TracksAuthor, SoftDeletes;

    protected $fillable = [
        'title', 'source_type', 'origin', 'original_url', 'file_path', 'file_hash', 'mime',
        'page_count', 'capture_date', 'document_date', 'sensitivity', 'ocr_text', 'retention_status',
    ];

    protected $casts = [
        'capture_date' => 'date',
        'document_date' => 'date',
        'page_count' => 'integer',
    ];

    public function scopeNotSealed(Builder $q): Builder
    {
        return $q->where('sensitivity', '!=', 'sealed');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function citations(): HasMany
    {
        return $this->hasMany(DocumentCitation::class);
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'document_entity_links')
            ->withPivot('note')
            ->withPivotValue('organization_id', Organization::currentId())
            ->withTimestamps();
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'document_story_links')
            ->withPivotValue('organization_id', Organization::currentId())
            ->withTimestamps();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->withPivotValue('organization_id', Organization::currentId())
            ->withTimestamps();
    }
}
