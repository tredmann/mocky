<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNoUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (User::count() === 0 && ! $request->is('register', 'login', 'forgot-password', 'reset-password/*', 'two-factor-challenge', 'mock/*', 'soap/*')) {
            return redirect()->route('register');
        }

        return $next($request);
    }
}
