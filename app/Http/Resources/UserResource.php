<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'school' => $this->whenLoaded('school', fn () => $this->school?->only(['id', 'name', 'code'])),
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
            'profile_pic_url' => $this->profile_pic ? route('school-files.avatar', ['userId' => $this->id, 'v' => substr(hash('sha256', $this->profile_pic), 0, 16)]) : null,
            'dashboard_path' => $this->dashboardPath(),
        ];
    }
}
