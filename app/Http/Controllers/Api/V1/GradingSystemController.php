<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grading\StoreGradingSystemRequest;
use App\Http\Requests\Grading\UpdateGradingSystemRequest;
use App\Models\GradingSystem;
use Illuminate\Http\JsonResponse;

class GradingSystemController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Grading system retrieved',
            'data' => GradingSystem::orderByDesc('max_marks')->get(),
        ]);
    }

    public function store(StoreGradingSystemRequest $request): JsonResponse
    {
        $grade = GradingSystem::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Grade created',
            'data' => $grade,
        ], 201);
    }

    public function show(GradingSystem $gradingSystem): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Grade retrieved',
            'data' => $gradingSystem,
        ]);
    }

    public function update(UpdateGradingSystemRequest $request, GradingSystem $gradingSystem): JsonResponse
    {
        $gradingSystem->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Grade updated',
            'data' => $gradingSystem->fresh(),
        ]);
    }

    public function destroy(GradingSystem $gradingSystem): JsonResponse
    {
        $gradingSystem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Grade deleted',
            'data' => null,
        ]);
    }
}
