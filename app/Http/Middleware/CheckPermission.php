<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    /**
     * Vérifier si l'utilisateur connecté possède la permission requise (RBAC dynamique).
     */
    public function handle($request, Closure $next, $permission)
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        if (!Auth::user()->hasPermission($permission)) {
            return response()->view('errors.403', [], 403);
        }

        return $next($request);
    }
}
