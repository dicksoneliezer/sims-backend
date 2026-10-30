<?php

namespace App\Http\Resources;

use App\Models\LowStockAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LowStockAlert
 */
class LowStockAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'item' => $this->whenLoaded('item', fn () => $this->item ? [
                'id' => $this->item->id,
                'item_code' => $this->item->item_code,
                'name' => $this->item->name,
                'unit' => $this->item->unit,
                'quantity' => $this->item->quantity,
                'reorder_level' => $this->item->reorder_level,
            ] : null),
            'threshold' => $this->threshold,
            'quantity_at_alert' => $this->quantity_at_alert,
            'is_resolved' => $this->is_resolved,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
