<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usádzač, mapa sály a prideľovanie miest. Hláška zámerne nespomína rolu –
 * používateľ nemá vedieť, že má obmedzený účet.
 */
class EnsureCanSeeSeating
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canSeeSeating()) {
            abort(403, 'Na túto stránku nemáte prístup.');
        }

        return $next($request);
    }
}
