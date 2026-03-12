<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventZone;
use App\Models\Lienzo;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(): JsonResponse
    {
        $events = Event::query()->with('lienzo', 'zones')->latest()->paginate(15);

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
            'zones' => ['nullable', 'array'],
            'zones.*.name' => ['required_with:zones', 'string', 'max:120'],
            'zones.*.capacity' => ['nullable', 'integer', 'min:1'],
            'zones.*.price' => ['required_with:zones', 'numeric', 'min:0'],
        ]);

        $capacity = collect($data['zones'] ?? [])->sum(fn ($zone) => (int) ($zone['capacity'] ?? 0));

        $lienzo = Lienzo::query()->firstOrCreate(
            [
                'nombre' => $data['venue'],
                'ciudad' => $data['city'],
            ],
            [
                'capacidad_total' => max(1, $capacity),
            ]
        );

        if ($capacity > 0 && $capacity > (int) $lienzo->capacidad_total) {
            $lienzo->update(['capacidad_total' => $capacity]);
        }

        $event = Event::query()->create([
            'id_lienzo' => $lienzo->id,
            'name' => $data['name'],
            'starts_at' => $data['starts_at'],
            'barcode_format' => $data['barcode_format'],
            'status' => $data['status'] ?? 'draft',
        ]);

        foreach (($data['zones'] ?? []) as $zone) {
            EventZone::query()->updateOrCreate(
                [
                    'id_lienzo' => $lienzo->id,
                    'nombre' => $zone['name'],
                ],
                [
                    'precio' => $zone['price'],
                ]
            );
        }

        return response()->json($event->load('zones'), 201);
    }

    public function show(Event $event): JsonResponse
    {
        return response()->json($event->load('zones', 'lienzo'));
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
            'zones.*.capacity' => ['nullable', 'integer', 'min:1'],
            'zones.*.price' => ['required_with:zones', 'numeric', 'min:0'],
        ]);

        if (isset($data['city'], $data['venue'])) {
            $lienzo = Lienzo::query()->firstOrCreate(
                [
                    'nombre' => $data['venue'],
                    'ciudad' => $data['city'],
                ],
                [
                    'capacidad_total' => max(1, collect($data['zones'] ?? [])->sum('capacity')),
                ]
            );

            $event->id_lienzo = $lienzo->id;
        }

        $event->fill($data);
        $event->save();

        if (isset($data['zones'])) {
            foreach ($data['zones'] as $zone) {
                EventZone::query()->updateOrCreate(
                    [
                        'id_lienzo' => $event->id_lienzo,
                        'nombre' => $zone['name'],
                    ],
                    [
                        'precio' => $zone['price'],
                    ]
                );
            }
        }

        return response()->json($event->load('zones', 'lienzo'));
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
            'validator_id' => ['required', 'exists:usuarios,id'],
        ]);

        $validator = User::query()->findOrFail($data['validator_id']);

        if ($validator->role !== 'validator') {
            return response()->json(['message' => 'El usuario no tiene rol validator'], 422);
        }

        return response()->json(['message' => 'Validador validado (sin asignacion persistente en este esquema)']);
    }

    public function unassignValidator(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'validator_id' => ['required', 'exists:usuarios,id'],
        ]);

        return response()->json(['message' => 'Validador removido']);
    }
}
