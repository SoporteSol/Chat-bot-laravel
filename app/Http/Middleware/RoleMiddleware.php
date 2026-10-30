<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        /** @var User $user */
        $user = Auth::user();

        // Comprobar el rol del usuario directamente desde la relación en base de datos
        if (!$user || !$user->hasRole(...$roles)) {
            abort(403, 'Acceso denegado: Tu rol asignado no cuenta con los permisos requeridos.');
        }

        return $next($request);
    }
}
