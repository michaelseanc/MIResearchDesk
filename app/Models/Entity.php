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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The universal subject record. People, organizations, committees, government bodies, projects,
 * and properties are all Entities differentiated by entity_type. Type-specific fields hang off
 * 1:1 profile tables. This uniformity is what lets relationships, search, and the graph work
 * consistently across every kind of subject.
 */
class Entity extends Model
{
    use BelongsToOrganization, HasUuid, TracksAuthor, SoftDeletes;

    /** Organization-shaped entity types (use the organization profile fields + org graph styling). */
    public const ORGANIZATION_TYPES = ['organization', 'government', 'pac', 'news', 'election_committee'];

    /** Human labels for the selectable entity types. */
    public const TYPE_LABELS = [
        'person' => 'Person',
        'organization' => 'Organization',
        'government' => 'Government',
        'pac' => 'PAC',
        'news' => 'News',
        'election_committee' => 'Election Committee',
    ];

    protected $fillable = [
        'entity_type', 'origin', 'display_name', 'photo_path', 'legal_name', 'status', 'primary_geography',
        'primary_jurisdiction_id', 'public_summary', 'internal_summary', 'why_it_matters',
        'sensitivity', 'last_reviewed_at', 'last_reviewed_by',
    ];

    protected $casts = ['last_reviewed_at' => 'datetime'];

    // --- Type scopes ---
    public function scopePeople(Builder $q): Builder
    {
        return $q->where('entity_type', 'person');
    }

    public function scopeOrganizations(Builder $q): Builder
    {
        return $q->where('entity_type', 'organization');
    }

    /** People are one shape; everything else (org, government, PAC, news) shares the org profile. */
    public function scopeOrganizationLike(Builder $q): Builder
    {
        return $q->whereIn('entity_type', self::ORGANIZATION_TYPES);
    }

    public function isOrganizationLike(): bool
    {
        return in_array($this->entity_type, self::ORGANIZATION_TYPES, true);
    }

    /** Hide sealed records from ordinary lists/search/exports. Sealed surfaces only in the vault. */
    public function scopeNotSealed(Builder $q): Builder
    {
        return $q->where('sensitivity', '!=', 'sealed');
    }

    // --- Profiles ---
    public function personProfile(): HasOne
    {
        return $this->hasOne(PersonProfile::class);
    }

    public function organizationProfile(): HasOne
    {
        return $this->hasOne(OrganizationProfile::class);
    }

    // --- Associations ---
    public function aliases(): HasMany
    {
        return $this->hasMany(EntityAlias::class);
    }

    public function contactMethods(): HasMany
    {
        return $this->hasMany(ContactMethod::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function relationshipsFrom(): HasMany
    {
        return $this->hasMany(Relationship::class, 'from_entity_id');
    }

    public function relationshipsTo(): HasMany
    {
        return $this->hasMany(Relationship::class, 'to_entity_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(PositionInterest::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ContactInteraction::class);
    }

    /** TRACER contributions this entity MADE (as the contributor). */
    public function donationsMade(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'contributor_entity_id')
            ->where('data_type', 'contributions');
    }

    /** TRACER contributions this entity RECEIVED (as the recipient committee). */
    public function donationsToCommittee(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'committee_entity_id')
            ->where('data_type', 'contributions');
    }

    /**
     * Backing relation for the "contributions by people employed here" panel. Matches TRACER's free-
     * text Employer field to this org's display name (exact). The relation manager widens this to
     * the org's other business names + variant-tolerant token matching via getTableQuery().
     */
    public function employeeContributions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'employer', 'display_name')
            ->where('data_type', 'contributions');
    }

    /**
     * Grammatical stopwords / corporate suffixes that shouldn't be REQUIRED when matching a name in
     * TRACER's free text (which often omits them, e.g. "PLATINUM GROUP" for "The Platinum Group").
     */
    public const NAME_NOISE = [
        'the', 'and', 'for', 'of', 'a', 'an',
        'llc', 'inc', 'corp', 'co', 'ltd', 'lp', 'llp', 'incorporated', 'corporation',
    ];

    /**
     * Names this entity is likely filed under in TRACER. A person appears by their personal name; a
     * business appears by its business name — display name, legal name, or DBA — so all are returned.
     *
     * @return array<int, string>
     */
    public function lookupNames(): array
    {
        $names = [$this->display_name];

        if ($this->entity_type === 'person') {
            $names[] = $this->personProfile?->full_name;
        } else {
            $names[] = $this->legal_name;
            $names[] = $this->organizationProfile?->dba_name;
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($n): string => trim((string) $n),
            $names,
        ))));
    }

    /**
     * Significant (noise-filtered) word tokens for each of this entity's lookup names.
     *
     * @return array<int, array<int, string>>
     */
    public function nameTokenGroups(): array
    {
        $groups = [];
        foreach ($this->lookupNames() as $name) {
            $tokens = collect(preg_split('/\s+/', mb_strtolower($name)))
                ->map(fn ($t) => trim($t, " \t.,&"))
                ->filter(fn ($t) => strlen($t) > 2 && ! in_array($t, self::NAME_NOISE, true))
                ->values()->all();
            if ($tokens !== []) {
                $groups[] = $tokens;
            }
        }

        return $groups;
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class, 'primary_jurisdiction_id');
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'story_entities')
            ->withPivot('role_note')
            ->withPivotValue('organization_id', Organization::currentId())
            ->withTimestamps();
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_entity_links')
            ->withPivot('note')
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
