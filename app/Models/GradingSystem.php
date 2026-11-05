<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class GradingSystem extends Model
{
    use BelongsToSchool;

    protected $table = 'grading_system';

    protected $fillable = ['grade', 'min_marks', 'max_marks', 'points', 'remarks'];

    protected function casts(): array
    {
        return [
            'min_marks' => 'decimal:2',
            'max_marks' => 'decimal:2',
            'points' => 'integer',
        ];
    }
}
