<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        $allowedOrigins = config('cors.allowed_origins');
        $allowedHeaders = config('cors.allowed_headers');
        $allowedMethod = config('cors.allowed_methods');
        $exposedHeaders = config('cors.exposed_headers');
        $maxAge = config('cors.max_age');
        $supportCredentials = config('cors.supports_credentials');

        if ($request->isMethod('GET') && $request->path() === 'sanctum/csrf-cookie') {
            $response = response('', 200);
        }

        $response->headers->set('Access-Control-Allow-Origin', implode(',', $allowedOrigins));
        $response->headers->set('Access-Control-Request-Headers', implode(',', $allowedOrigins));
        $response->headers->set('Access-Control-Allow-Methods', implode(',', $allowedMethod));
        $response->headers->set('Access-Control-Allow-Headers', implode(',', $allowedHeaders));
        $response->headers->set('Access-Control-Max-Age', $maxAge);
        $response->headers->set('Access-Control-Expose-Headers', implode(',', $exposedHeaders));
        $response->headers->set('Access-Control-Allow-Credentials', $supportCredentials);

        return $response;
    }
}
