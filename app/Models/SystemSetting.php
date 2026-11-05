<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use BelongsToSchool;

    protected $fillable = ['key', 'value', 'description'];
}
