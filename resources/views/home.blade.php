@extends('layouts.app')

@section('title', 'Finde dein nächstes Abenteuer')

@section('content')
    <div class="space-y-8">
        <div class="hero-lead space-y-3">
            <h1>Finde dein nächstes DSA-Abenteuer</h1>
            <p class="max-w-prose text-lg text-muted">
                Sag uns, wonach ihr sucht – Regeln, Helden, Region und mehr. Die Auswahl schränkt sich mit jeder Angabe ein, danach schlagen wir passende Abenteuer für „Das Schwarze Auge“ vor.
            </p>
        </div>

        <livewire:adventure-wizard />
    </div>
@endsection
