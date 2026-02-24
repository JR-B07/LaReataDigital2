<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::query()
            ->with('zones')
            ->where('status', 'published')
            ->orderBy('starts_at');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($date = $request->string('date')->toString()) {
            $query->whereDate('starts_at', $date);
        }

        return response()->json($query->paginate(12));
    }

    public function show(Event $event): JsonResponse
    {
        $event->load('zones');

        return response()->json($event);
    }
}
