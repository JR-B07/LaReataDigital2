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
            ->with('zones', 'lienzo')
            ->where('estatus', 'activo')
            ->orderBy('fecha')
            ->orderBy('hora');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('lienzo', function ($lq) use ($search) {
                        $lq->where('ciudad', 'like', "%{$search}%");
                    });
            });
        }

        if ($date = $request->string('date')->toString()) {
            $query->whereDate('fecha', $date);
        }

        return response()->json($query->paginate(12));
    }

    public function show(Event $event): JsonResponse
    {
        $event->load('zones', 'lienzo');

        return response()->json($event);
    }
}
