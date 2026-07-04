<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The atomic unit of evidence: a specific page / paragraph / quote within a document. Relationships,
 * positions, and claims all cite these rows.
 */
class DocumentCitation extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['document_id', 'page', 'paragraph', 'quote', 'image_ref', 'note'];

    protected $casts = ['page' => 'integer'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** Human-readable label for pickers, e.g. "Planning packet — p.14". */
    public function getLabelAttribute(): string
    {
        $doc = $this->document?->title ?? 'Document';
        return $this->page ? "{$doc} — p.{$this->page}" : $doc;
    }
}
