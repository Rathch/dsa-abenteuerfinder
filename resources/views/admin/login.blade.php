{{--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only
--}}
@extends('layouts.app')

@section('title', 'Verwaltung')

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <article class="card legal-prose max-w-md mx-auto">
        <h1>Verwaltung</h1>
        <p class="text-muted">Zugang nur mit Passwort. Dieser Bereich ist nicht öffentlich verlinkt.</p>

        <form method="post" action="{{ route('admin.login.store') }}" class="mt-6 space-y-4" novalidate>
            @csrf

            <div class="space-y-2">
                <label for="password" class="block font-serif text-xl font-bold">Passwort</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="field-input"
                    autocomplete="current-password"
                    required
                    aria-describedby="{{ $errors->has('password') ? 'password-error' : '' }}"
                    @error('password') aria-invalid="true" @enderror
                >
                @error('password')
                    <p class="error-text" id="password-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">Anmelden</button>
        </form>
    </article>
@endsection
