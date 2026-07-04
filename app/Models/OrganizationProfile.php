<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1:1 extension of an Entity of type "organization". Note: `organization_id` here is the TENANT,
 * not a self-reference — the org this profile describes is `entity_id`.
 */
class OrganizationProfile extends Model
{
    use BelongsToOrganization;

    protected $primaryKey = 'entity_id';
    public $incrementing = false;

    protected $fillable = [
        'entity_id', 'dba_name', 'org_subtype', 'website', 'registration_number',
        'registered_agent', 'jurisdiction_id',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }
}
