<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogRequests
{
    public function handle(Request $request, Closure $next)
    {
        // Log le type de méthode de requête
        Log::info('Request Method: '.$request->method());

        // Log les détails de la requête
        Log::info('Request Details:', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        // Passer à la prochaine middleware
        return $next($request);
    }
}
