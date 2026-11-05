<?php

namespace App\Models;

use App\Models\Scopes\SchoolScope;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function administrators(): HasMany
    {
        return $this->hasMany(User::class)->withoutGlobalScope(SchoolScope::class)->where('role', 'super_admin');
    }
}
