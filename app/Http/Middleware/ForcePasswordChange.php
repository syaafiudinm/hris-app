<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memaksa user yang belum mengganti password default untuk menuju
 * halaman ganti password. Hanya route /ganti-password dan /logout
 * yang diizinkan lewat.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->must_change_password
            && ! $request->routeIs('password.*')
            && ! $request->routeIs('logout')
        ) {
            return redirect()->route('password.edit')
                ->with('warning', 'Silakan ubah password Anda terlebih dahulu sebelum melanjutkan.');
        }

        return $next($request);
    }
}
