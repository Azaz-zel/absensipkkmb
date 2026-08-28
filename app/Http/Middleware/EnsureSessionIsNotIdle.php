<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsNotIdle
{
    public function handle(Request $request, Closure $next): Response
    {
        $lastActivity = $request->session()->get('last_active_at');
        $lifetimeSeconds = config('session.lifetime') * 60;

        if ($lastActivity && (time() - $lastActivity) > $lifetimeSeconds) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => 'Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan login kembali.',
            ]);
        }

        $request->session()->put('last_active_at', time());

        return $next($request);
    }
}
