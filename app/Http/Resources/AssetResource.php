<?php

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asset
 */
class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_code' => $this->asset_code,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'location' => $this->location,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_cost' => $this->purchase_cost,
            'current_value' => $this->current_value,
            'depreciation_rate' => $this->depreciation_rate,
            'custodian_id' => $this->custodian_id,
            'custodian' => $this->whenLoaded('custodian', fn () => $this->custodian ? [
                'id' => $this->custodian->id,
                'full_name' => $this->custodian->full_name,
            ] : null),
            'status' => $this->status,
            'condition' => $this->condition,
            'maintenance_count' => $this->whenCounted('maintenance'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
