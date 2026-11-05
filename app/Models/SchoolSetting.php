<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use BelongsToSchool;

    protected $fillable = ['key', 'value', 'group'];
}
