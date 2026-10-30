<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parent\StoreParentRequest;
use App\Http\Requests\Parent\UpdateParentRequest;
use App\Http\Resources\ParentResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ParentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $parents = User::query()
            ->where('role', 'parent')
            ->withCount('children')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('full_name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Parents retrieved',
            'data' => ParentResource::collection($parents->items()),
            'meta' => [
                'current_page' => $parents->currentPage(),
                'per_page' => $parents->perPage(),
                'total' => $parents->total(),
                'last_page' => $parents->lastPage(),
            ],
        ]);
    }

    public function store(StoreParentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $children = Arr::pull($data, 'children', []);
        $data['role'] = 'parent';

        $parent = User::create($data);
        $this->syncChildren($parent, $children);

        return response()->json([
            'success' => true,
            'message' => 'Parent created',
            'data' => new ParentResource($parent->fresh()->load('children')->loadCount('children')),
        ], 201);
    }

    public function show(User $parent): JsonResponse
    {
        $this->ensureParent($parent);

        return response()->json([
            'success' => true,
            'message' => 'Parent retrieved',
            'data' => new ParentResource($parent->load('children')->loadCount('children')),
        ]);
    }

    public function update(UpdateParentRequest $request, User $parent): JsonResponse
    {
        $this->ensureParent($parent);

        $data = $request->validated();
        $hasChildren = array_key_exists('children', $data);
        $children = Arr::pull($data, 'children', []);

        $parent->fill($data)->save();

        if ($hasChildren) {
            $this->syncChildren($parent, $children);
        }

        return response()->json([
            'success' => true,
            'message' => 'Parent updated',
            'data' => new ParentResource($parent->fresh()->load('children')->loadCount('children')),
        ]);
    }

    public function destroy(User $parent): JsonResponse
    {
        $this->ensureParent($parent);

        $parent->tokens()->delete();
        $parent->delete();

        return response()->json([
            'success' => true,
            'message' => 'Parent deleted',
            'data' => null,
        ]);
    }

    private function ensureParent(User $parent): void
    {
        abort_unless($parent->role === 'parent', 404, 'Parent not found.');
    }

    private function syncChildren(User $parent, array $children): void
    {
        $payload = collect($children)
            ->unique('student_id')
            ->mapWithKeys(fn (array $child) => [
                $child['student_id'] => [
                    'relationship' => $child['relationship'] ?? null,
                    'is_primary' => (bool) ($child['is_primary'] ?? false),
                ],
            ])
            ->all();

        $parent->children()->sync($payload);
    }
}
