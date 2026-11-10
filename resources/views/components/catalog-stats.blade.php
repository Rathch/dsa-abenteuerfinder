@php
    $percentLabel = static function (float $share): string {
        $formatted = number_format($share, 2, ',', '');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    };
@endphp

<section class="card space-y-8" aria-labelledby="catalog-stats-heading">
    <div class="space-y-2">
        <h2 id="catalog-stats-heading">Im Katalog</h2>
        <p class="stat-total">{{ number_format($total, 0, ',', '.') }}</p>
        <p class="text-muted">Abenteuer, die der Finder vorschlagen kann.</p>
        <p>
            <a href="#abenteuer-finden" class="btn btn-primary">Abenteuer finden</a>
        </p>
    </div>

    <div class="grid gap-10 sm:grid-cols-2">
        @foreach ($groups as $group)
            <div class="catalog-chart flex flex-col gap-4">
                <h3 id="catalog-chart-{{ $loop->index }}">{{ $group['title'] }}</h3>
                @if ($group['items'] === [])
                    <p>Noch keine Angaben.</p>
                @else
                    <svg
                        class="pie-chart"
                        viewBox="0 0 36 36"
                        role="img"
                        aria-labelledby="catalog-chart-{{ $loop->index }} catalog-chart-{{ $loop->index }}-alt"
                    >
                        <desc id="catalog-chart-{{ $loop->index }}-alt">Tortendiagramm. Name, Anzahl und Anteil stehen in der Liste darunter.</desc>
                        @foreach ($group['items'] as $index => $item)
                            @if ($item['share'] > 0)
                                <path class="pie-slice" data-slice="{{ $index }}" fill="var(--chart-{{ ($index % 8) + 1 }})" d="{{ $item['path'] }}" />
                            @endif
                        @endforeach
                    </svg>
                    <ul class="mx-auto w-full max-w-sm space-y-1.5" aria-labelledby="catalog-chart-{{ $loop->index }}">
                        @foreach ($group['items'] as $index => $item)
                            <li class="pie-legend" data-slice="{{ $index }}">
                                <span class="sr-only">{{ $item['label'] }}, {{ number_format($item['count'], 0, ',', '.') }} Abenteuer, {{ $percentLabel($item['share']) }} Prozent.</span>
                                <span class="grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-4 text-sm" aria-hidden="true">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="pie-swatch" style="background: var(--chart-{{ ($index % 8) + 1 }})"></span>
                                        <span>{{ $item['label'] }}</span>
                                    </span>
                                    <span class="shrink-0 tabular-nums">
                                        <span class="font-semibold">{{ number_format($item['count'], 0, ',', '.') }}</span>
                                        <span class="text-muted">{{ $percentLabel($item['share']) }} %</span>
                                    </span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</section>
