<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResidentIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->hasRole(User::ROLE_RESIDENT) && ! $user->isApproved()) {
            return redirect()->route('approval.pending');
        }

        return $next($request);
    }
}
