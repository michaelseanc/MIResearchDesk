<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named, reloadable relationship-graph configuration (focus entity + depth + filters).
 * Org-scoped so the whole newsroom shares the same set of saved views.
 */
class SavedGraphView extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'params',
    ];

    protected $casts = [
        'params' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
