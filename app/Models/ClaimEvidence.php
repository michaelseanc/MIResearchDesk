<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimEvidence extends Model
{
    use BelongsToOrganization;

    protected $table = 'claim_evidence';

    protected $fillable = ['claim_id', 'document_citation_id', 'note'];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function citation(): BelongsTo
    {
        return $this->belongsTo(DocumentCitation::class, 'document_citation_id');
    }
}
