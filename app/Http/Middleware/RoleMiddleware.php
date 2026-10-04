<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!$request->user()->is_active) {
            auth()->logout();
            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated.'
            ]);
        }

        if (!empty($roles) && !$request->user()->hasAnyRole($roles)) {
            abort(403, 'Unauthorized. Required role: ' . implode(', ', $roles));
        }

        return $next($request);
    }
}