@extends('layouts.app')

@section('title', 'Datenschutz')

@section('content')
    <article class="card legal-prose max-w-3xl">
        <h1>Datenschutzerklärung</h1>

        <p>
            Der DSA-Abenteuerfinder ist bewusst datensparsam gebaut. Hier steht kurz und verständlich, was passiert, wenn du die Seite nutzt.
        </p>

        <h2>Kein Konto, keine Anmeldung</h2>
        <p>
            Du musst dich nicht registrieren oder anmelden. Wir erheben keine Namen, E-Mail-Adressen oder andere Kontaktdaten.
            Die öffentliche Seite bleibt ohne Konto. Eine nicht verlinkte Verwaltung nutzt ein Passwort und dieselbe Sitzung, ohne Namen oder E-Mail.
        </p>

        <h2>Deine Antworten im Wizard</h2>
        <ul>
            <li>Deine Antworten werden ausschließlich für die Dauer deiner Sitzung zwischengespeichert, damit du zurückblättern und die Seite neu laden kannst.</li>
            <li>Sie sind nicht mit deiner Person verknüpft und werden nicht ausgewertet, weitergegeben oder für Statistiken verwendet.</li>
            <li>Mit „Neu starten“ löschst du sie sofort; andernfalls verfallen sie automatisch, wenn die Sitzung abläuft.</li>
        </ul>

        <h2>Cookies und lokaler Speicher</h2>
        <ul>
            <li>Technisch notwendige Sitzungs-Cookies halten deine Wizard-Sitzung zusammen und schützen vor Formularmissbrauch (CSRF). Dafür ist keine Einwilligung nötig.</li>
            <li>Ohne eigene Wahl folgt die Seite der Systemeinstellung deines Browsers. Erst wenn du den Schalter „Dunkles Design“ umlegst, speichert dein Browser die Wahl lokal (<code>localStorage</code>, Schlüssel <code>theme</code>). Dieser Wert verlässt dein Gerät nicht.</li>
        </ul>

        <h2>Kein Tracking</h2>
        <p>
            Wir setzen keine Analyse-, Werbe- oder Tracking-Dienste ein und binden keine externen Schriftarten, Skripte oder Karten ein.
        </p>

        <h2>Server-Logdaten</h2>
        <p>
            Beim Aufruf der Seite verarbeitet der Webserver technisch bedingt kurzzeitig deine IP-Adresse und Angaben zum Abruf (Zeitpunkt, URL, Browser),
            um die Seite auszuliefern und Missbrauch abzuwehren. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO. Die Daten werden nach kurzer Zeit gelöscht.
        </p>

        <h2>Externe Links</h2>
        <p>
            In den Empfehlungen verlinken wir auf das Wiki Aventurica. Diese Links öffnen in einem neuen Tab und sind entsprechend gekennzeichnet.
            Erst wenn du sie anklickst, verlässt du unser Angebot; ab dann gelten die Datenschutzhinweise der jeweiligen Betreiber.
            Beim bloßen Anzeigen der Empfehlungen werden keine Daten an diese Seiten übertragen.
        </p>

        <h2>Deine Rechte</h2>
        <p>
            Dir stehen nach der DSGVO Rechte auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch zu.
            Außerdem kannst du dich bei einer Datenschutzaufsichtsbehörde beschweren. Kontaktdaten findest du im
            <a href="{{ route('impressum') }}" class="text-link">Impressum</a>.
        </p>
    </article>
@endsection
