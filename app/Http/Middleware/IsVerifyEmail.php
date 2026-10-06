<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IsVerifyEmail
{
    /**
     * Exige que el usuario tenga el email verificado.
     *
     * Ojo: el campo real es users.email_verified_at; antes chequeaba
     * 'email_verificated_at' (inexistente), así que siempre habría fallado.
     * Sigue sin estar activado en Kernel.php: habilitarlo es una decisión aparte.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->email_verified_at) {
            throw new HttpException(403, 'Usuario sin validar cuenta');
        }

        return $next($request);
    }
}
