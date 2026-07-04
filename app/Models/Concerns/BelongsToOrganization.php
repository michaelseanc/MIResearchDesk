<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenancy enforcement. Every model using this trait is:
 *   1. auto-stamped with the current organization_id on create, and
 *   2. globally scoped to the current organization on every query.
 *
 * "Current organization" resolves from the authenticated user (or an explicitly set context in
 * console/seeder/job code via Organization::useOrganization()). When no organization can be
 * resolved (e.g. an unauthenticated console command), the scope is skipped so owner/superadmin
 * tooling can operate cross-tenant deliberately.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->organization_id) && ($id = Organization::currentId()) !== null) {
                $model->organization_id = $id;
            }
        });

        static::addGlobalScope('organization', function (Builder $builder): void {
            if (($id = Organization::currentId()) !== null) {
                $builder->where($builder->getModel()->getTable() . '.organization_id', $id);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
