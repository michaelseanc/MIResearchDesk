<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'import_batch_id', 'source', 'data_type', 'year',
        'file_number', 'committee_type', 'committee_name', 'candidate_name', 'contributor_type',
        'contributor_name', 'address', 'city', 'state', 'jurisdiction', 'zip', 'occupation', 'employer', 'txn_subtype',
        'description', 'amount', 'transaction_date', 'received_by', 'amended',
        'committee_entity_id', 'candidate_entity_id', 'contributor_entity_id', 'match_state',
        'row_hash', 'notes', 'source_extra',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'year' => 'integer',
        'source_extra' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FinanceImportBatch::class, 'import_batch_id');
    }

    public function committeeEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'committee_entity_id');
    }

    public function candidateEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'candidate_entity_id');
    }

    public function contributorEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'contributor_entity_id');
    }
}
