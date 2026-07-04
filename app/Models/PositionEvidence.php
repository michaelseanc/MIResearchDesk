<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionEvidence extends Model
{
    use BelongsToOrganization;

    protected $table = 'position_evidence';

    protected $fillable = ['position_id', 'document_citation_id', 'note'];

    public function position(): BelongsTo
    {
        return $this->belongsTo(PositionInterest::class, 'position_id');
    }

    public function citation(): BelongsTo
    {
        return $this->belongsTo(DocumentCitation::class, 'document_citation_id');
    }
}
