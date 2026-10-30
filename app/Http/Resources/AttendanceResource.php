<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'date' => $this->date?->toDateString(),
            'status' => $this->status,
            'remarks' => $this->remarks,
            'marked_by' => $this->marked_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
