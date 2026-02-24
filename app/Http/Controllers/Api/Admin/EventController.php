<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(): JsonResponse
    {
        $events = Event::query()->with('zones', 'validators:id,name,email')->latest()->paginate(15);

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:120'],
            'venue' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'barcode_format' => ['required', 'in:qr,code128'],
            'status' => ['nullable', 'in:draft,published,canceled'],
            'zones' => ['required', 'array', 'min:1'],
            'zones.*.name' => ['required', 'string', 'max:120'],
            'zones.*.capacity' => ['required', 'integer', 'min:1'],
            'zones.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $event = Event::query()->create([
            'organizer_id' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'city' => $data['city'],
            'venue' => $data['venue'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'barcode_format' => $data['barcode_format'],
            'status' => $data['status'] ?? 'draft',
        ]);

        $event->zones()->createMany($data['zones']);

        return response()->json($event->load('zones'), 201);
    }

    public function show(Event $event): JsonResponse
    {
        return response()->json($event->load('zones', 'validators:id,name,email'));
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'city' => ['sometimes', 'required', 'string', 'max:120'],
            'venue' => ['sometimes', 'required', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'barcode_format' => ['sometimes', 'required', 'in:qr,code128'],
            'status' => ['sometimes', 'required', 'in:draft,published,canceled'],
            'zones' => ['sometimes', 'array', 'min:1'],
            'zones.*.name' => ['required_with:zones', 'string', 'max:120'],
            'zones.*.capacity' => ['required_with:zones', 'integer', 'min:1'],
            'zones.*.price' => ['required_with:zones', 'numeric', 'min:0'],
        ]);

        $event->update($data);

        if (isset($data['zones'])) {
            $event->zones()->delete();
            $event->zones()->createMany($data['zones']);
        }

        return response()->json($event->load('zones', 'validators:id,name,email'));
    }

    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return response()->json(['message' => 'Evento eliminado']);
    }

    public function publish(Event $event): JsonResponse
    {
        $event->update(['status' => 'published']);

        return response()->json(['message' => 'Evento publicado']);
    }

    public function cancel(Event $event): JsonResponse
    {
        $event->update(['status' => 'canceled']);

        return response()->json(['message' => 'Evento cancelado']);
    }

    public function assignValidator(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'validator_id' => ['required', 'exists:users,id'],
        ]);

        $validator = User::query()->findOrFail($data['validator_id']);

        if ($validator->role !== 'validator') {
            return response()->json(['message' => 'El usuario no tiene rol validator'], 422);
        }

        $event->validators()->syncWithoutDetaching([$validator->id]);

        return response()->json(['message' => 'Validador asignado']);
    }

    public function unassignValidator(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'validator_id' => ['required', 'exists:users,id'],
        ]);

        $event->validators()->detach($data['validator_id']);

        return response()->json(['message' => 'Validador removido']);
    }
}
