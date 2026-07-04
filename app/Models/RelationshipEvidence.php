<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelationshipEvidence extends Model
{
    use BelongsToOrganization;

    protected $table = 'relationship_evidence';

    protected $fillable = ['relationship_id', 'document_citation_id', 'note'];

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }

    public function citation(): BelongsTo
    {
        return $this->belongsTo(DocumentCitation::class, 'document_citation_id');
    }
}
