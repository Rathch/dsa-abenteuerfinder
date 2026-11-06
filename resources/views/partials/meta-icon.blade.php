{{--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only

Erwartet $icon: map | hourglass | bulb
--}}
@php
    $icon = $icon ?? '';
@endphp
<svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($icon)
        @case('map')
            {{-- Faltkarte mit Standortmarker --}}
            <path d="M3 6.5 9 4l6 2.5L21 4v13.5L15 20l-6-2.5L3 20Z" />
            <path d="M9 4v13.5" />
            <path d="M15 6.5V20" />
            <circle cx="12" cy="11.5" r="1.6" fill="currentColor" stroke="none" />
            @break
        @case('hourglass')
            <path d="M6 3h12" />
            <path d="M6 21h12" />
            <path d="M8 3v3.2a4 4 0 0 0 1.6 3.2L12 12l2.4-2.6A4 4 0 0 0 16 6.2V3" />
            <path d="M8 21v-3.2a4 4 0 0 1 1.6-3.2L12 12l2.4 2.6a4 4 0 0 1 1.6 3.2V21" />
            @break
        @case('bulb')
            <path d="M9 18h6" />
            <path d="M10 21h4" />
            <path d="M12 3a6 6 0 0 0-3.6 10.8c.7.6 1.1 1.4 1.2 2.2h4.8c.1-.8.5-1.6 1.2-2.2A6 6 0 0 0 12 3Z" />
            @break
    @endswitch
</svg>
