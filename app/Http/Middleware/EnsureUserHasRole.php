<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole($roles)) {
            abort(403, 'You do not have permission to access this area.');
        }

        $requiresApproval = $user->hasAnyRole([User::ROLE_RESIDENT, User::ROLE_BARANGAY]);

        if ($requiresApproval && ! $user->isApproved() && ! $request->routeIs('approval.pending')) {
            return redirect()->route('approval.pending');
        }

        return $next($request);
    }
}
