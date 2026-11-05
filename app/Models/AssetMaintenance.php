<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenance extends Model
{
    use BelongsToSchool;

    protected $table = 'asset_maintenance';

    protected $fillable = [
        'asset_id',
        'maintenance_date',
        'description',
        'cost',
        'performed_by',
        'next_due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_date' => 'date',
            'next_due_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
