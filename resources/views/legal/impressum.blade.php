@extends('layouts.app')

@section('title', 'Impressum')

@section('content')
    <article class="card legal-prose max-w-3xl">
        <h1>Impressum</h1>

        <h2>Angaben gemäß § 5 DDG</h2>
        <address class="legal-address">
            <p class="legal-name">Christian Rath-Ulrich</p>
            <p>Ernst-Mühlendyck-Str 2<br>51143 Köln</p>
        </address>
        <p><strong>Vertreten durch:</strong><br>Christian Rath-Ulrich</p>

        <h2>Kontakt</h2>
        <p>
            Telefon: ---<br>
            E-Mail: <a class="text-link" href="mailto:kontakt@rath-ulrich.de">kontakt@rath-ulrich.de</a>
        </p>

        <h2>Verbraucherstreitbeilegung / Universalschlichtungsstelle</h2>
        <p>
            Wir nehmen nicht an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teil und sind dazu auch nicht verpflichtet.
        </p>

        <h2>Datenschutzerklärung</h2>
        <p>
            Unter dem folgenden Link finden Sie <a class="text-link" href="{{ route('datenschutz') }}">unsere Datenschutzerklärung</a>.
        </p>

        <h2>Haftungsausschluss</h2>
        <p><strong>Haftung für Links</strong></p>
        <p>
            Unser Angebot enthält Links zu externen Webseiten Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar. Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen.
        </p>

        <h2>Urheberrecht</h2>
        <p>
            Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers. Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen Gebrauch gestattet. Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.
        </p>

        <h2>Hinweis zu Marken und Fan-Richtlinien</h2>
        <p>
            Dies ist ein inoffizielles, nicht kommerzielles Fanprojekt und steht in keiner Verbindung zur Ulisses Spiele GmbH.
            Es werden keine offiziellen Logos, Cover oder sonstigen Grafiken des Verlags verwendet.
        </p>
        <p>
            DAS SCHWARZE AUGE, AVENTURIEN, DERE, MYRANOR, THARUN, UTHURIA, RIESLAND und THE DARK EYE sind eingetragene Marken der Ulisses Spiele GmbH, Waldems.
            Eine Verwendung über die
            <a href="https://ulisses-spiele.de/fan-richtlinie/" class="text-link" target="_blank" rel="noopener noreferrer">Fan-Richtlinien der Ulisses Spiele<span class="sr-only"> (öffnet in neuem Tab)</span></a>
            hinaus darf nur nach vorheriger schriftlicher Genehmigung der Ulisses Medien und Spiel Distribution GmbH erfolgen.
        </p>

        <p class="legal-note">
            Erstellt mit dem Impressum-Generator von
            <a class="text-link" href="https://www.websitewissen.com/" target="_blank" rel="noopener noreferrer">WebsiteWissen.com<span class="sr-only"> (öffnet in neuem Tab)</span></a>,
            dem Ratgeber für Website-Erstellung, Homepage-Baukästen und Shopsysteme. Rechtstext von der Kanzlei Hasselbach.
        </p>
    </article>
@endsection
