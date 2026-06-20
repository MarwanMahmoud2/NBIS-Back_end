<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role (ده البراميتر اللي هنبعت فيه nurse أو police)
     */
    public function handle(Request $request, Closure $next, string ...$role): Response
    {
        $user = $request->user();

        // 1. التأكد إن المستخدم مسجل دخول أصلاً (Sanctum/API compatible)
        if (! $user) {
            if ($this->expectsApiResponse($request)) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect('login');
        }

        // 2. التأكد إن الـ role بتاع المستخدم هو نفسه الـ role المطلوب للمسار ده
        if (! in_array($user->role, $role, true)) {
            $message = 'Unauthorized action. This area is for ' . implode(', ', $role) . ' only.';

            if ($this->expectsApiResponse($request)) {
                return response()->json(['message' => $message], 403);
            }

            // لو مش هو، ارمي خطأ 403 (غير مسموح)
            abort(403, $message);
        }

        return $next($request);
    }

    /** طلبات الـ API / JSON ترجع أخطاء بدل إعادة التوجيه لصفحة الدخول */
    private function expectsApiResponse(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
