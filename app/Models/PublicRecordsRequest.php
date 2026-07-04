<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicRecordsRequest extends Model
{
    use BelongsToOrganization, TracksAuthor;

    protected $fillable = [
        'story_id', 'agency', 'subject', 'submitted_at', 'due_at', 'status', 'response_note',
    ];

    protected $casts = [
        'submitted_at' => 'date',
        'due_at' => 'date',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
