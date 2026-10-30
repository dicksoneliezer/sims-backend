<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Dormitory extends Model
{
    protected $table = 'dormitories';

    protected $fillable = [
        'name',
        'gender',
        'capacity',
        'matron_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function matron(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matron_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function beds(): HasManyThrough
    {
        return $this->hasManyThrough(
            Bed::class,
            Room::class,
            'dormitory_id',
            'room_id',
            'id',
            'id'
        );
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function rollCalls(): HasMany
    {
        return $this->hasMany(RollCall::class);
    }
}