<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AllowCors
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, PUT, DELETE');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Auth-Token, Origin, Authorization, X-CSRF-TOKEN');
        return $response;
    }
}
