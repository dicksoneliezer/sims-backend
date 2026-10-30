<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ParentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'last_login' => $this->last_login?->toIso8601String(),
            'children_count' => $this->whenCounted('children'),
            'children' => $this->whenLoaded('children', fn () => $this->children->map(fn ($child) => [
                'id' => $child->id,
                'student_number' => $child->student_number,
                'full_name' => $child->full_name,
                'class' => $child->class,
                'relationship' => $child->pivot->relationship,
                'is_primary' => (bool) $child->pivot->is_primary,
            ])),
        ];
    }
}
