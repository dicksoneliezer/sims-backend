<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolFileService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolFileController extends Controller
{
    public function avatar(Request $request, int $userId, SchoolFileService $files): StreamedResponse
    {
        $user = $userId === (int) $request->user()->id ? $request->user() : User::findOrFail($userId);

        return $files->response($user->profile_pic);
    }

    public function studentPhoto(Student $student, SchoolFileService $files): StreamedResponse
    {
        return $files->response($student->photo);
    }

    public function receipt(Expense $expense, SchoolFileService $files): StreamedResponse
    {
        return $files->response($expense->receipt);
    }
}
