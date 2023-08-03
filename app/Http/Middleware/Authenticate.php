<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Authenticate extends Middleware
{

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        Log::info('method is: ' . $request->method() . ' acceptsAnyContentType ' . $request->acceptsAnyContentType() . ' - acceptsJson ' . $request->acceptsJson() . ' - bearerToken ' . $request->bearerToken() . ' - expectsJson ' . $request->expectsJson());
        //return $request->expectsJson() ? null : route('login'); así estaba antes
        return $request->expectsJson() ? null : route('login');
    }
}
