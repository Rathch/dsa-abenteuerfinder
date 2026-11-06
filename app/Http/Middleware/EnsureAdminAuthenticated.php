<?php

// SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-only

namespace App\Http\Middleware;

use App\Services\AdminAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leitet unauthentifizierte Anfragen auf das Verwaltungs-Login um.
 * Ohne konfiguriertes Passwort antwortet der Bereich mit 404.
 */
class EnsureAdminAuthenticated
{
    public function __construct(
        private readonly AdminAuthService $auth,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->auth->isConfigured()) {
            abort(404);
        }

        if (! $this->auth->isAuthenticated()) {
            return redirect()->guest(route('admin.login'));
        }

        return $next($request);
    }
}
