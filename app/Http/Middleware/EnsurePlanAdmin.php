<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege la configuración INTERNA del corte de planes.
 *
 * Son rutas que prenden y apagan el tope de todos los contribuyentes, asignan planes
 * y corren el recálculo: no son de cliente y no pueden quedar detrás de "estás
 * logueado", porque cualquier cuenta de cualquier empresa podría apagar el corte.
 *
 * DOS FORMAS DE ENTRAR:
 *   1. el encabezado X-Plan-Admin-Token con el secreto de operación;
 *   2. un usuario autenticado con el permiso CREAR COMPAÑIA (el permiso más cercano a
 *      "dueño de la empresa" que ya existe en el proyecto).
 *
 * Si el secreto NO está configurado, la vía 1 queda cerrada (nunca un token vacío
 * como contraseña válida) y la puesta en marcha se hace por consola.
 */
final class EnsurePlanAdmin
{
    public const HEADER = 'X-Plan-Admin-Token';

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('plans.admin_token');
        $enviado = (string) $request->header(self::HEADER, '');

        if ($token !== '' && $enviado !== '' && hash_equals($token, $enviado)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user !== null && method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('CREAR COMPAÑIA')) {
            return $next($request);
        }

        abort(403, 'La configuración interna de planes no está disponible para este usuario.');
    }
}
