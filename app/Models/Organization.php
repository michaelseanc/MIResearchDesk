<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Services\Finance\FinanceNetworkBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The tenant. Also the single source of truth for "which organization are we operating as right
 * now" — used by the BelongsToOrganization global scope across every other model.
 */
class Organization extends Model
{
    use HasUuid, SoftDeletes;

    protected static ?int $currentId = null;

    protected $fillable = ['name', 'slug', 'status', 'settings'];

    protected $casts = ['settings' => 'array'];

    /** Resolve the active tenant id: explicit context first, then the authenticated user. */
    public static function currentId(): ?int
    {
        return static::$currentId ?? auth()->user()?->organization_id;
    }

    /** Pin the active tenant explicitly (seeders, queued jobs, console, cross-tenant admin). */
    public static function useOrganization(?int $id): void
    {
        static::$currentId = $id;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The donor-network auto-build thresholds for an org, falling back to the builder defaults for
     * any unset value. Stored under settings.finance_network by the TRACER import dialog.
     *
     * @return array{min_committee_total: float, min_donor_total: float, max_donors_per_committee: int}
     */
    public static function networkConfig(?int $organizationId = null): array
    {
        $organizationId ??= static::currentId();
        $settings = static::withoutGlobalScopes()->find($organizationId)?->settings ?? [];
        $cfg = $settings['finance_network'] ?? [];

        return [
            'min_committee_total' => (float) ($cfg['min_committee_total'] ?? FinanceNetworkBuilder::DEFAULT_MIN_COMMITTEE_TOTAL),
            'min_donor_total' => (float) ($cfg['min_donor_total'] ?? FinanceNetworkBuilder::DEFAULT_MIN_DONOR_TOTAL),
            'max_donors_per_committee' => (int) ($cfg['max_donors_per_committee'] ?? FinanceNetworkBuilder::DEFAULT_MAX_DONORS_PER_COMMITTEE),
        ];
    }
}
