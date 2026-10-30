<?php

namespace App\Http\Resources;

use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTransaction
 */
class StockTransactionResource extends JsonResource
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
            ] : null),
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'student_number' => $this->student->student_number,
                'full_name' => $this->student->full_name,
            ] : null),
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_value' => (float) $this->quantity * (float) $this->unit_price,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
