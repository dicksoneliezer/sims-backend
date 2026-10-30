<?php

namespace App\Services;

use App\Models\GradingSystem;

class GradingService
{
    public function grade(float $marks): array
    {
        $grade = GradingSystem::where('min_marks', '<=', $marks)
            ->where('max_marks', '>=', $marks)
            ->orderByDesc('min_marks')
            ->first();

        return [
            'grade' => $grade?->grade,
            'points' => $grade?->points,
            'remarks' => $grade?->remarks,
        ];
    }
}
