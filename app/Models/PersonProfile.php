<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonProfile extends Model
{
    use BelongsToOrganization;

    protected $primaryKey = 'entity_id';
    public $incrementing = false;

    protected $fillable = [
        'entity_id', 'full_name', 'known_names', 'professional_role', 'current_company',
        'current_company_entity_id', 'linkedin_url', 'geography_detail',
        'source_status', 'confidentiality_status', 'dossier_summary', 'reliability_notes',
    ];

    protected static function booted(): void
    {
        // When a person is linked to a company entity, ensure an "employed_by" connection exists so
        // the employment shows on the relationship graph. Existing connections are never downgraded.
        static::saved(function (PersonProfile $profile): void {
            $companyId = $profile->current_company_entity_id;
            if (! $companyId || ! $profile->entity_id || $companyId === $profile->entity_id) {
                return;
            }

            $type = RelationshipType::where('name', 'employed_by')->first();
            if (! $type) {
                return;
            }

            Relationship::firstOrCreate(
                [
                    'from_entity_id' => $profile->entity_id,
                    'to_entity_id' => $companyId,
                    'relationship_type_id' => $type->id,
                ],
                [
                    'status' => 'active',
                    'verification_state' => 'reported',
                    'sensitivity' => 'internal',
                    'notes' => 'Current company (linked from the person’s dossier).',
                ],
            );
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'current_company_entity_id');
    }
}
