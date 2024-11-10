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
        if ($request->isMethod('OPTIONS')) {
            // Handling preflight requests
            return response([], 204)
                ->header('Access-Control-Allow-Origin', implode(',', config('cors.allowed_origins')))
                ->header('Access-Control-Allow-Methods', implode(',', config('cors.allowed_methods')))
                ->header('Access-Control-Allow-Headers', implode(',', config('cors.allowed_headers')))
                ->header('Access-Control-Max-Age', config('cors.max_age'));
        }

        $response = $next($request);

        // Set CORS headers
        $response->headers->set('Access-Control-Allow-Origin', implode(',', config('cors.allowed_origins')));
        $response->headers->set('Access-Control-Allow-Methods', implode(',', config('cors.allowed_methods')));
        $response->headers->set('Access-Control-Allow-Headers', implode(',', config('cors.allowed_headers')));
        $response->headers->set('Access-Control-Expose-Headers', implode(',', config('cors.exposed_headers')));
        $response->headers->set('Access-Control-Max-Age', config('cors.max_age'));
        $response->headers->set('Access-Control-Allow-Credentials', config('cors.supports_credentials') ? 'true' : 'false');

        // Set security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
