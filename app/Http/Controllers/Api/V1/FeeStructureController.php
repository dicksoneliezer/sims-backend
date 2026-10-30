<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeeStructure\StoreFeeStructureRequest;
use App\Http\Requests\FeeStructure\UpdateFeeStructureRequest;
use App\Http\Resources\FeeStructureResource;
use App\Models\FeeStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeStructureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $fees = FeeStructure::query()
            ->with('schoolClass:id,name')
            ->withCount('invoices')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('school_level'), fn ($query) => $query->where('school_level', $request->string('school_level')))
            ->when($request->filled('term'), fn ($query) => $query->where('term', $request->string('term')))
            ->when($request->filled('academic_year'), fn ($query) => $query->where('academic_year', $request->string('academic_year')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Fee structures retrieved',
            'data' => FeeStructureResource::collection($fees->items()),
            'meta' => [
                'current_page' => $fees->currentPage(),
                'per_page' => $fees->perPage(),
                'total' => $fees->total(),
                'last_page' => $fees->lastPage(),
            ],
        ]);
    }

    public function store(StoreFeeStructureRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $fee = FeeStructure::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Fee structure created',
            'data' => new FeeStructureResource($fee->load('schoolClass:id,name')->loadCount('invoices')),
        ], 201);
    }

    public function show(FeeStructure $feeStructure): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Fee structure retrieved',
            'data' => new FeeStructureResource($feeStructure->load('schoolClass:id,name')->loadCount('invoices')),
        ]);
    }

    public function update(UpdateFeeStructureRequest $request, FeeStructure $feeStructure): JsonResponse
    {
        $feeStructure->fill($request->validated())->save();

        return response()->json([
            'success' => true,
            'message' => 'Fee structure updated',
            'data' => new FeeStructureResource($feeStructure->fresh()->load('schoolClass:id,name')->loadCount('invoices')),
        ]);
    }

    public function destroy(FeeStructure $feeStructure): JsonResponse
    {
        if ($feeStructure->invoices()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This fee structure has invoices and cannot be deleted.',
                'code' => 422,
            ], 422);
        }

        $feeStructure->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fee structure deleted',
            'data' => null,
        ]);
    }
}
