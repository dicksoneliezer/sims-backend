<?php

namespace App\Http\Resources;

use App\Models\AssetMaintenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssetMaintenance
 */
class AssetMaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'asset' => $this->whenLoaded('asset', fn () => $this->asset ? [
                'id' => $this->asset->id,
                'asset_code' => $this->asset->asset_code,
                'name' => $this->asset->name,
            ] : null),
            'maintenance_date' => $this->maintenance_date?->toDateString(),
            'description' => $this->description,
            'cost' => $this->cost,
            'performed_by' => $this->performed_by,
            'next_due_date' => $this->next_due_date?->toDateString(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
