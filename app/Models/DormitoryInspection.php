<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DormitoryInspection extends Model
{
    use BelongsToSchool;

    protected $table = 'dormitory_inspections';

    protected $fillable = [
        'dormitory_id',
        'inspector_id',
        'inspection_date',
        'hygiene_score',
        'cleanliness_score',
        'issues_found',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'hygiene_score' => 'integer',
            'cleanliness_score' => 'integer',
        ];
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
