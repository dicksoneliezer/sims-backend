<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicAnnouncement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicAnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $announcements = AcademicAnnouncement::where('sender_id', auth()->id())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'body' => $item->body,
                'audience' => $item->audience,
                'created_at' => $item->created_at,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Announcements retrieved',
            'data' => $announcements,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'body' => 'required|string',
            'audience' => 'required|string|in:all_teachers,all_students,all',
        ]);

        $announcement = AcademicAnnouncement::create([
            'sender_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'audience' => $validated['audience'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Announcement sent successfully',
            'data' => $announcement,
        ], 201);
    }
}