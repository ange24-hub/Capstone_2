<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ResidentMobileAccess
{
    public function handle(Request $request, Closure $next, string $approval = 'any')
    {
        $user = $request->user();
        abort_unless($user?->hasRole(User::ROLE_RESIDENT), 403, 'This app is for resident accounts only.');
        abort_unless($user->currentAccessToken() instanceof PersonalAccessToken
            && $user->tokenCan('resident:mobile'), 403, 'Sign in through the resident app.');
        if ($approval === 'approved') {
            abort_unless($user->isApproved() && $user->barangay, 403, 'Your account needs barangay approval before using resident services.');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
