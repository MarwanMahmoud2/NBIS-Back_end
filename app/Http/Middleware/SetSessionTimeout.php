<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSessionTimeout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $user = $request->user();
            $settings = $user->settings ?? [];

            // Get session timeout from user settings, default to 30 minutes
            $sessionTimeout = $settings['session_timeout'] ?? 30;

            // If set to 0, never expire (use very long lifetime)
            if ($sessionTimeout == 0) {
                config(['session.lifetime' => 525600]); // 1 year
            } else {
                config(['session.lifetime' => $sessionTimeout]);
            }
        }

        return $next($request);
    }
}
