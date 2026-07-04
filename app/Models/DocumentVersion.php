<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['document_id', 'version_relationship', 'file_path', 'file_hash', 'note'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
