<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use BelongsToSchool;

    protected $table = 'rooms';

    protected $fillable = [
        'dormitory_id',
        'room_number',
        'capacity',
        'occupied',
    ];

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
