<?php

namespace App\Http\Resources;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryItem
 */
class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_code' => $this->item_code,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'quantity' => $this->quantity,
            'reorder_level' => $this->reorder_level,
            'unit_price' => $this->unit_price,
            'total_value' => (float) $this->quantity * (float) $this->unit_price,
            'location' => $this->location,
            'is_active' => $this->is_active,
            'is_low_stock' => $this->isLowStock(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
