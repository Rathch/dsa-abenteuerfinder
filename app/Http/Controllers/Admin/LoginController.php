<?php

// SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-only

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly AdminAuthService $auth,
    ) {}

    public function show(): View|RedirectResponse
    {
        if ($this->auth->isAuthenticated()) {
            return redirect()->route('admin.adventures.index');
        }

        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-login:'.$request->ip();
        $rateLimited = ! (bool) config('admin.disable_login_rate_limit');

        if ($rateLimited && RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'password' => "Zu viele Versuche. Bitte in {$seconds} Sekunden erneut versuchen.",
            ]);
        }

        if (! $this->auth->attempt($validated['password'])) {
            if ($rateLimited) {
                RateLimiter::hit($key, 60);
            }

            throw ValidationException::withMessages([
                'password' => 'Das Passwort ist nicht korrekt.',
            ]);
        }

        RateLimiter::clear($key);

        return redirect()->intended(route('admin.adventures.index'));
    }

    public function destroy(): RedirectResponse
    {
        $this->auth->logout();

        return redirect()->route('admin.login');
    }
}
