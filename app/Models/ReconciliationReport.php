<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationReport extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'bank_statement_id',
        'period_start',
        'period_end',
        'matched_count',
        'unmatched_count',
        'total_matched',
        'status',
        'generated_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'matched_count' => 'integer',
            'unmatched_count' => 'integer',
            'total_matched' => 'decimal:2',
        ];
    }

    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
