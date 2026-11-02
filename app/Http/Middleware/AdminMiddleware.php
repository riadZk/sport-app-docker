<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden. Admin access required.',
                'results' => null,
            ], 403);
        }

        return $next($request);
    }
}
