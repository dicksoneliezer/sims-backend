<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSchoolSettingsRequest;
use App\Models\SchoolSetting;
use Illuminate\Http\JsonResponse;

class SchoolSettingController extends Controller
{
    public const KEYS = [
        'school_name',
        'school_motto',
        'school_address',
        'school_phone',
        'school_email',
        'school_website',
        'head_teacher_name',
        'academic_year',
        'current_term',
        'currency',
        'timezone',
    ];

    public function show(): JsonResponse
    {
        $settings = SchoolSetting::whereIn('key', self::KEYS)->pluck('value', 'key');

        $data = [];
        foreach (self::KEYS as $key) {
            $data[$key] = $settings[$key] ?? null;
        }

        return response()->json([
            'success' => true,
            'message' => 'School settings retrieved',
            'data' => $data,
        ]);
    }

    public function update(UpdateSchoolSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated() as $key => $value) {
            SchoolSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'school']
            );
        }

        return $this->show();
    }
}
