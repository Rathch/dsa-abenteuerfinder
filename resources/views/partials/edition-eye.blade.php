{{-- SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich --}}
{{-- SPDX-License-Identifier: GPL-3.0-only --}}
@php
    $edition = (int) ($edition ?? 0);
@endphp
@if ($edition >= 1 && $edition <= 5)
    <svg class="edition-eye" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
        <path class="eye-sclera" d="M1.6 16.7C4.8 8.4 26.4 7.8 30.4 15.8C26.6 24.2 5.6 24.8 1.6 16.7Z" />
        <path class="eye-lid" d="M2.1 16.1C5.4 9.1 25.6 8.5 29.6 15.4" />
        <circle class="eye-iris" cx="16" cy="16.2" r="6.5" />
        <circle class="eye-pupil" cx="16" cy="16.2" r="5.05" />
        <text class="eye-number" x="16" y="16.45" text-anchor="middle" dominant-baseline="central">{{ $edition }}</text>
    </svg>
@endif
