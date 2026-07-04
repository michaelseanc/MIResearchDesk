<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryTask extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['story_id', 'title', 'assigned_to', 'due_at', 'status'];

    protected $casts = ['due_at' => 'datetime'];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
