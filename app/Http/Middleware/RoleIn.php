<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleIn
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        if (empty($roles)) {
            return $next($request);
        }

        if ($user->hasAnyRole($roles)) {
            return $next($request);
        }

        // Backward compatibility for older records where only is_admin is set.
        if (in_array('regional_director', $roles, true) && (bool) $user->is_admin) {
            return $next($request);
        }

        abort(403, 'Insufficient role privileges.');
    }
}

