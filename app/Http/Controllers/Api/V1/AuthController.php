<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\User;
use App\Services\SchoolContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        // =========================================
        // Kubali login kwa username, email, au phone
        // =========================================
        $login = $credentials['username'] ?? $credentials['login'] ?? '';

        $matches = User::withoutGlobalScope(SchoolScope::class)
            ->where(fn ($query) => $query->where('username', $login)->orWhere('email', $login)->orWhere('phone', $login))->get();
        $user = $matches->count() === 1 ? $matches->first() : null;

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            if ($user) {
                $this->recordFailedLogin($user);
            }

            return $this->error('Invalid credentials', 401);
        }

        if ($user->role !== 'system_admin' && (! $user->school_id || ! School::whereKey($user->school_id)->where('is_active', true)->exists())) {
            return $this->error('Your school is not active. Contact the administrator.', 403);
        }

        if (! $user->is_active) {
            return $this->error('Your account has been deactivated. Contact the administrator.', 403);
        }

        if ($user->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Account temporarily locked due to failed login attempts.',
                'code' => 423,
                'data' => ['locked_until' => $user->locked_until->toIso8601String()],
            ], 423);
        }

        app(SchoolContext::class)->run($user->school_id, fn () => $user->forceFill([
            'login_attempts' => 0,
            'locked_until' => null,
            'last_login' => now(),
            'last_ip' => $request->ip(),
        ])->save());

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($user->load('school:id,name,code')),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
            'data' => null,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user',
            'data' => new UserResource($request->user()->load('school:id,name,code')),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json([
            'success' => true,
            'message' => __($status),
            'data' => null,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                app(SchoolContext::class)->run($user->school_id, fn () => $user->forceFill([
                    'password' => $password,
                    'login_attempts' => 0,
                    'locked_until' => null,
                ])->save());

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json([
            'success' => true,
            'message' => __($status),
            'data' => null,
        ]);
    }

    private function recordFailedLogin(User $user): void
    {
        $user->login_attempts++;

        if ($user->login_attempts >= User::MAX_LOGIN_ATTEMPTS) {
            $user->locked_until = now()->addMinutes(User::LOCK_MINUTES);
        }

        app(SchoolContext::class)->run($user->school_id, fn () => $user->save());
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $status,
        ], $status);
    }
}
