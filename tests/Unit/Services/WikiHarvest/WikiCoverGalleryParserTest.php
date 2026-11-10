<?php

declare(strict_types=1);

use App\Services\WikiHarvest\WikiCoverGalleryParser;

it('maps an adventure page to its cover thumbnail', function (): void {
    $html = <<<'HTML'
    <ul>
    <li class="gallerybox">
        <a href="/wiki/Datei:AB_VA1.jpg" title="Offenbarung des Himmels"></a>
        <a href="/wiki/Offenbarung_des_Himmels" title="Offenbarung des Himmels">
            <img src="/de/images/thumb/1/1b/AB_VA1.jpg/106px-AB_VA1.jpg" alt="">
        </a>
    </li>
    </ul>
    HTML;

    $covers = (new WikiCoverGalleryParser)->parse($html);

    expect($covers)->toBe([
        '/wiki/Offenbarung_des_Himmels' => 'https://de.wiki-aventurica.de/de/images/thumb/1/1b/AB_VA1.jpg/250px-AB_VA1.jpg',
    ]);
});
