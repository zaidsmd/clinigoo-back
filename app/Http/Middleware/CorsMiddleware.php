<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin') ?: '*';

        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, Cache-Control, Pragma');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Private-Network', 'true');

        return $response;
    }
}

