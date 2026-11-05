<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Services\SchoolContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class SetSchoolContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403, 'Your account is not active.');
        $schoolId = $user->role === 'system_admin' ? null : $user->school_id;

        if ($user->role !== 'system_admin') {
            abort_unless($schoolId && School::whereKey($schoolId)->where('is_active', true)->exists(), 403, 'Your school is not active.');
            foreach (array_keys(Arr::dot($request->all())) as $key) {
                abort_if($key === 'school_id' || str_ends_with($key, '.school_id'), 422, 'The school is determined by your account.');
            }
        }

        return app(SchoolContext::class)->run($schoolId === null ? null : (int) $schoolId, fn () => $next($request));
    }
}
