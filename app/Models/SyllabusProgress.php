<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyllabusProgress extends Model
{
    protected $fillable = ['syllabus_topic_id', 'teacher_id', 'status', 'completion_date', 'remarks'];

    protected function casts(): array
    {
        return ['completion_date' => 'date'];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(SyllabusTopic::class, 'syllabus_topic_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
