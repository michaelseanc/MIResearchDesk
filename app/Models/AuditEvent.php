<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Tamper-evident, APPEND-ONLY audit ledger. Records access/edit/download/export of sensitive
 * material — the accountability spine the future source vault depends on. Updates and deletes are
 * blocked at the model layer; each row hash-chains to the previous one so gaps/edits are detectable.
 */
class AuditEvent extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null; // insert-only: created_at only

    protected $fillable = [
        'organization_id', 'user_id', 'action', 'auditable_type', 'auditable_id',
        'sensitivity_touched', 'ip', 'user_agent', 'context', 'payload_hash', 'prev_hash',
    ];

    protected $casts = ['context' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Audit events are append-only and cannot be modified.'));
        static::deleting(fn () => throw new RuntimeException('Audit events are append-only and cannot be deleted.'));
    }

    /**
     * Append an audit event, chaining its hash to the previous event for the same tenant.
     */
    public static function record(string $action, ?Model $subject = null, array $context = [], ?string $sensitivity = null): self
    {
        $orgId = Organization::currentId();

        $prev = static::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->latest('id')
            ->first();

        $payload = json_encode([
            'org' => $orgId,
            'user' => auth()->id(),
            'action' => $action,
            'type' => $subject?->getMorphClass(),
            'id' => $subject?->getKey(),
            'sensitivity' => $sensitivity,
            'context' => $context,
            'at' => now()->toIso8601String(),
        ]);

        return static::create([
            'organization_id' => $orgId,
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'sensitivity_touched' => $sensitivity,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'context' => $context,
            'payload_hash' => hash('sha256', (string) $payload),
            'prev_hash' => $prev?->payload_hash,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
