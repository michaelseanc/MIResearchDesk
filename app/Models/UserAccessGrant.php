<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Narrows a user's access to specific records (a single story/entity) beyond what their role
 * grants. Consulted by policies when finer-grained access than role defaults is required.
 */
class UserAccessGrant extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['user_id', 'grantable_type', 'grantable_id', 'ability', 'granted_by', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantable(): MorphTo
    {
        return $this->morphTo();
    }
}
