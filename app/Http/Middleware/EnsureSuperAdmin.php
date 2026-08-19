<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Prístup len pre super administrátora – správa používateľov a auditný log. */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Táto časť je dostupná len super administrátorovi.');
        }

        return $next($request);
    }
}
