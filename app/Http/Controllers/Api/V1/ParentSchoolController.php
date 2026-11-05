<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentSchoolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Parents may see the school directory, but never other schools' accounts or records.
        $schools = School::query()->select(['id', 'name', 'code'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$request->user()->school_id])
            ->orderBy('name')->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 24), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $schools->map(fn (School $school) => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
                'is_my_school' => (int) $school->id === (int) $request->user()->school_id,
            ])->all(),
            'meta' => ['current_page' => $schools->currentPage(), 'last_page' => $schools->lastPage(), 'total' => $schools->total()],
        ]);
    }

    public function show(Request $request, School $school): JsonResponse
    {
        abort_unless((int) $school->id === (int) $request->user()->school_id, 403, 'You have no access to this school.');

        return response()->json([
            'success' => true,
            'data' => ['school' => $school->only(['id', 'name', 'code']), 'dashboard_path' => '/parent/dashboard'],
        ]);
    }
}
