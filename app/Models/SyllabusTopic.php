<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyllabusTopic extends Model
{
    protected $fillable = [
        'subject_id',
        'class_id',
        'title',
        'description',
        'term',
        'week',
        'sort_order',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function progresses(): HasMany
    {
        return $this->hasMany(SyllabusProgress::class);
    }
}
