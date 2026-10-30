<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'description', 'head_id'];

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }
}
