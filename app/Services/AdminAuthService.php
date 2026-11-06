<?php

// SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-only

namespace App\Services;

use Illuminate\Support\Facades\Hash;

/**
 * Sitzungsbasiertes Passwort-Gate für den unverlinkten Verwaltungsbereich.
 */
class AdminAuthService
{
    public function isConfigured(): bool
    {
        $hash = config('admin.password_hash');

        return is_string($hash) && $hash !== '';
    }

    public function isAuthenticated(): bool
    {
        return (bool) session()->get($this->sessionKey(), false);
    }

    public function attempt(string $password): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $hash = (string) config('admin.password_hash');
        if (! Hash::check($password, $hash)) {
            return false;
        }

        session()->put($this->sessionKey(), true);
        session()->regenerate();

        return true;
    }

    public function logout(): void
    {
        session()->forget($this->sessionKey());
        session()->regenerate();
    }

    private function sessionKey(): string
    {
        return (string) config('admin.session_key', 'admin_authenticated');
    }
}
