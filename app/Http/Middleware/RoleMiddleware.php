<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $userRole = $user?->role;

        if (! $user || ! in_array($userRole, $roles, true)) {
            return response()->json([
                'message' => 'No autorizado para este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
