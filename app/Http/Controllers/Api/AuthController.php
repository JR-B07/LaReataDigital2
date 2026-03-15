<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'El registro público está deshabilitado.',
        ], 403);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'intended_roles' => ['nullable', 'array'],
            'intended_roles.*' => ['string', 'in:admin,seller,validator'],
        ]);

        $login = trim((string) ($data['login'] ?? $data['email'] ?? ''));

        if ($login === '') {
            return response()->json([
                'message' => 'Debes indicar tu usuario o correo.',
            ], 422);
        }

        $normalizedLogin = strtolower(str_replace(' ', '', $login));

        $user = User::query()
            ->whereRaw('LOWER(usuario) = ?', [strtolower($login)])
            ->orWhereRaw("REPLACE(LOWER(nombre), ' ', '') = ?", [$normalizedLogin])
            ->first();

        if (! $user || ! $user->activo || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        $intendedRoles = $data['intended_roles'] ?? [];

        if (! empty($intendedRoles) && ! in_array($user->role, $intendedRoles, true)) {
            return response()->json([
                'message' => 'Esta cuenta no tiene acceso a este módulo.',
            ], 403);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }
}
