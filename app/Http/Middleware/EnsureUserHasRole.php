<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $roles)
    {
        if (! Auth::check()) {
            abort(401);
        }

        if (Auth::user()->isSuspended()) {
            abort(403, 'Your account is suspended.');
        }

        $allowed = array_map(
            static fn (string $role): string => User::normalizeRole(trim($role)),
            explode('-', $roles)
        );

        if (! in_array(User::normalizeRole(Auth::user()->role), $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
