<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Usuarios::query();

        if ($request->filled('role')) {
            $roleMap = [
                'admin' => ['administrador', 'superadministrador'],
                'seller' => ['vendedor', 'promotor'],
                'validator' => ['checador'],
            ];
            $dbRoles = $roleMap[$request->query('role')] ?? [$request->query('role')];
            $query->whereIn('rol', $dbRoles);
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('nombre', 'like', "%{$s}%")
                    ->orWhere('usuario', 'like', "%{$s}%")
                    ->orWhere('telefono', 'like', "%{$s}%");
            });
        }

        $users = $query->orderByDesc('id')->get();

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'usuario' => ['required', 'string', 'max:100', 'unique:usuarios,usuario'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'rol' => ['required', 'string', Rule::in(['administrador', 'vendedor', 'checador', 'promotor'])],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $data['activo'] = true;

        $user = Usuarios::create($data);

        return response()->json($user, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = Usuarios::findOrFail($id);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'usuario' => ['required', 'string', 'max:100', Rule::unique('usuarios', 'usuario')->ignore($user->id)],
            'telefono' => ['nullable', 'string', 'max:20'],
            'rol' => ['required', 'string', Rule::in(['administrador', 'vendedor', 'checador', 'promotor'])],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($user->fresh());
    }

    public function toggleActive(int $id): JsonResponse
    {
        $user = Usuarios::findOrFail($id);
        $user->activo = !$user->activo;
        $user->save();

        return response()->json($user);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Usuarios::findOrFail($id);
        $user->activo = false;
        $user->save();

        return response()->json(['message' => 'Usuario desactivado.']);
    }
}
