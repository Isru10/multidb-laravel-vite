<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        // 1. Super Admins bypass this check (they can see all clinics)
        if ($user->is_super_admin) {
            return $next($request);
        }

        // 2. Check if the normal user actually belongs to the active tenant
        // tenant('id') gets the current clinic ID from the URL dynamically!
        if (!$user->tenants()->where('tenants.id', tenant('id'))->exists()) {
            return response()->json(['error' => 'Unauthorized. You do not belong to this clinic.'], 403);
        }

        return $next($request);
    }
}
