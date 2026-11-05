<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreSchoolRequest;
use App\Http\Requests\School\UpdateSchoolRequest;
use App\Http\Resources\UserResource;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\SchoolContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchoolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schools = School::query()->with('administrators:id,school_id,username,full_name,email,phone,is_active')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })->orderBy('name')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return response()->json([
            'success' => true, 'data' => $schools->items(),
            'meta' => ['current_page' => $schools->currentPage(), 'last_page' => $schools->lastPage(), 'total' => $schools->total()],
        ]);
    }

    public function store(StoreSchoolRequest $request): JsonResponse
    {
        $data = $request->validated();
        [$school, $admin] = DB::transaction(function () use ($data): array {
            $school = School::create(['name' => $data['name'], 'code' => $data['code'] ?? Str::uuid()->toString(), 'is_active' => true]);
            if (empty($data['code'])) {
                $code = 'SCH-'.str_pad((string) $school->id, 6, '0', STR_PAD_LEFT);
                while (School::where('code', $code)->exists()) {
                    $code = 'SCH-'.$school->id.'-'.Str::upper(Str::random(8));
                }
                $school->update(['code' => $code]);
            }
            $admin = app(SchoolContext::class)->run($school->id, function () use ($data): User {
                $admin = User::create(collect($data['admin'])->except('password_confirmation')->all() + ['role' => 'super_admin', 'is_active' => true]);
                SchoolSetting::create(['key' => 'school_name', 'value' => $data['name'], 'group' => 'school']);

                return $admin;
            });

            return [$school, $admin];
        });

        return response()->json(['success' => true, 'message' => 'School and administrator created.', 'data' => ['school' => $school, 'admin' => new UserResource($admin)]], 201);
    }

    public function update(UpdateSchoolRequest $request, School $school): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($school, $data): void {
            $school->update($data);
            if (array_key_exists('name', $data)) {
                app(SchoolContext::class)->run($school->id, fn () => SchoolSetting::updateOrCreate(['key' => 'school_name'], ['value' => $data['name'], 'group' => 'school']));
            }
        });

        return response()->json(['success' => true, 'message' => 'School updated.', 'data' => $school->fresh()]);
    }
}
