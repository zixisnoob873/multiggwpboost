<?php

namespace App\Http\Middleware;

use App\Support\AdminPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminHasAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        abort_unless(AdminPermission::userCan($request->user(), $ability), 403);

        return $next($request);
    }
}
