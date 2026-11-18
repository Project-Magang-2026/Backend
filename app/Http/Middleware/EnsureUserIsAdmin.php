<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya Super Admin yang dapat mengelola resource ini.',
            ], 403);
        }

        return $next($request);
    }
}