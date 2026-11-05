<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RollCall extends Model
{
    use BelongsToSchool;

    protected $table = 'roll_calls';

    protected $fillable = [
        'dormitory_id',
        'session',
        'date',
        'taken_by',
        'total_present',
        'total_absent',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_present' => 'integer',
            'total_absent' => 'integer',
        ];
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }
}
