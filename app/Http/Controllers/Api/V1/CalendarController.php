<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\StoreCalendarRequest;
use App\Http\Requests\Calendar\UpdateCalendarRequest;
use App\Models\SchoolCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 25), 100);

        $events = SchoolCalendar::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('end_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('start_date', '<=', $request->date('to')))
            ->orderBy('start_date')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Calendar events retrieved',
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'last_page' => $events->lastPage(),
            ],
        ]);
    }

    public function store(StoreCalendarRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['type'] = $data['type'] ?? 'event';
        $data['created_by'] = $request->user()->id;

        $event = SchoolCalendar::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Calendar event created',
            'data' => $event,
        ], 201);
    }

    public function show(SchoolCalendar $calendarEvent): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Calendar event retrieved',
            'data' => $calendarEvent,
        ]);
    }

    public function update(UpdateCalendarRequest $request, SchoolCalendar $calendarEvent): JsonResponse
    {
        $data = $request->validated();
        $data['type'] = $data['type'] ?? 'event';
        $calendarEvent->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Calendar event updated',
            'data' => $calendarEvent->fresh(),
        ]);
    }

    public function destroy(SchoolCalendar $calendarEvent): JsonResponse
    {
        $calendarEvent->delete();

        return response()->json([
            'success' => true,
            'message' => 'Calendar event deleted',
            'data' => null,
        ]);
    }
}
