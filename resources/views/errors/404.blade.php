{{--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only
--}}
@extends('layouts.app')

@section('title', 'Seite nicht gefunden')

@section('content')
    <div class="error-page">
        <div class="error-mark" aria-hidden="true">
            @include('partials.logo-swords')
        </div>

        <div class="hero-lead error-copy space-y-3">
            <h1>Seite nicht gefunden</h1>
            <p class="max-w-prose text-lg text-muted">
                Diese Spur führt ins Leere. Die gesuchte Seite gibt es hier nicht – oder sie ist weitergezogen.
            </p>
        </div>

        <p>
            <a href="{{ route('home') }}" class="btn btn-primary">Zur Startseite</a>
        </p>
    </div>
@endsection
