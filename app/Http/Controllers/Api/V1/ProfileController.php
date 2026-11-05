<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Rules\UniqueLoginIdentifier;
use App\Services\SchoolFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved',
            'data' => new UserResource($request->user()->load('school:id,name,code')),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', 'unique:users,email,'.$user->id, new UniqueLoginIdentifier($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', new UniqueLoginIdentifier($user->id)],
        ]);

        $user->fill($validated)->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated',
            'data' => new UserResource($user->fresh()->load('school:id,name,code')),
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->profile_pic) {
            app(SchoolFileService::class)->delete($user->profile_pic);
        }

        $path = app(SchoolFileService::class)->store($request->file('avatar'), 'avatars');
        $user->forceFill(['profile_pic' => $path])->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated',
            'data' => new UserResource($user->fresh()->load('school:id,name,code')),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->forceFill(['password' => $validated['password']])->save();
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed. Please log in again.',
            'data' => null,
        ]);
    }
}
