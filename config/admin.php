<?php

// SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-only

return [

    /*
    |--------------------------------------------------------------------------
    | Admin-Passwort-Hash
    |--------------------------------------------------------------------------
    |
    | bcrypt-Hash für den Zugang unter /verwaltung. Ohne gesetzten Wert antwortet
    | die Route mit 404. Erzeugen z. B. mit:
    | php -r "echo password_hash('geheim', PASSWORD_BCRYPT, ['cost' => 12]), PHP_EOL;"
    |
    */
    'password_hash' => env('ADMIN_PASSWORD_HASH'),

    /*
    |--------------------------------------------------------------------------
    | Session-Schlüssel
    |--------------------------------------------------------------------------
    */
    'session_key' => 'admin_authenticated',

    /*
    |--------------------------------------------------------------------------
    | Playwright / E2E
    |--------------------------------------------------------------------------
    |
    | In isolierten Browser-Läufen entfällt die Anmelde-Drosselung, damit
    | parallele Worker sich nicht gegenseitig aussperren.
    |
    */
    'disable_login_rate_limit' => (bool) env('PLAYWRIGHT_E2E', false),

];
