<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceImportBatch extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'source', 'data_type', 'year', 'source_url', 'raw_file_path', 'file_hash',
        'source_last_modified', 'status', 'filter', 'rows_total', 'rows_imported',
        'rows_skipped', 'network_committees', 'network_connections', 'error', 'started_at', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'filter' => 'array',
        'source_last_modified' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'import_batch_id');
    }
}
