<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'nin_number' => $this->nin_number,
            'employment_id' => $this->employment_id,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'last_login' => $this->last_login?->toIso8601String(),
            'profile_pic' => $this->profile_pic,
            'profile_pic_url' => $this->profile_pic ? Storage::disk('public')->url($this->profile_pic) : null,
            'dashboard_path' => $this->dashboardPath(),
        ];
    }
}
