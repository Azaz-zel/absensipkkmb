<?php

namespace App\Http\Middleware;

use App\Models\Kelas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClassAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $next($request);
        }

        $routeKelas = $request->route('kelas');
        $kelasId = $routeKelas instanceof Kelas ? $routeKelas->id : ($routeKelas ?? $request->route('kelas_id'));

        abort_unless($kelasId && $user->kelas()->where('kelas.id', $kelasId)->exists(), 403);

        return $next($request);
    }
}
