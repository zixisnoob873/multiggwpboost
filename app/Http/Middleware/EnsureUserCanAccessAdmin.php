<?php

namespace App\Http\Middleware;

use App\Support\AdminPermission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserCanAccessAdmin
{
    public function handle(Request $request, Closure $next, ?string $module = null, ?string $ability = null)
    {
        if (! Auth::check()) {
            abort(401);
        }

        $user = $request->user();

        if (! $user?->isAdminUser()) {
            abort(403);
        }

        if ($user->isSuspended()) {
            abort(403, 'Your account is suspended.');
        }

        if ($module !== null && ! $user->canAccessAdminModule($module)) {
            abort(403);
        }

        if ($ability !== null && ! AdminPermission::userCan($user, $ability)) {
            abort(403);
        }

        return $next($request);
    }
}
