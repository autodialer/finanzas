<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EsAdminOGerente
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || auth()->user()->isCapturista()) {
            abort(403, 'No tienes permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}
