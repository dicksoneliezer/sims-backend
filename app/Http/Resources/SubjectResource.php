<?php

namespace App\Http\Resources;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subject
 */
class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'level' => $this->level,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'results_count' => $this->whenCounted('examResults'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
