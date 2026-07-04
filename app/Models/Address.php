<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'entity_id', 'label', 'line1', 'line2', 'city', 'state', 'postal_code',
        'country', 'is_primary', 'sensitivity', 'notes',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** Single-line rendering for tables/labels. */
    public function getOneLineAttribute(): string
    {
        return collect([$this->line1, $this->line2, $this->city, $this->state, $this->postal_code])
            ->filter()
            ->implode(', ');
    }
}
