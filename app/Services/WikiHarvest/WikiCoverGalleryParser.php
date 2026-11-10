<?php

declare(strict_types=1);

namespace App\Services\WikiHarvest;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Ordnet Abenteuer-Pfade den Cover-Vorschaubildern der Wiki-Galerie zu.
 */
final class WikiCoverGalleryParser
{
    public const PAGE_URL = 'https://de.wiki-aventurica.de/wiki/Abenteuer/Galerie';

    /**
     * @return array<string, string> Wiki-Pfad (/wiki/…) => absolute Bild-URL
     */
    public function parse(string $html): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $boxes = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " gallerybox ")]');
        if ($boxes === false) {
            return [];
        }

        $covers = [];
        foreach ($boxes as $box) {
            if (! $box instanceof DOMElement) {
                continue;
            }

            $path = $this->adventurePath($box);
            $image = $this->imageUrl($box);
            if ($path === null || $image === null || isset($covers[$path])) {
                continue;
            }

            $covers[$path] = $image;
        }

        return $covers;
    }

    private function adventurePath(DOMElement $box): ?string
    {
        foreach ($box->getElementsByTagName('a') as $anchor) {
            $href = rawurldecode($anchor->getAttribute('href'));
            if (! str_starts_with($href, '/wiki/') || str_starts_with($href, '/wiki/Datei:')) {
                continue;
            }

            $path = explode('?', $href, 2)[0];

            return $path !== '/wiki/' ? $path : null;
        }

        return null;
    }

    private function imageUrl(DOMElement $box): ?string
    {
        $image = $box->getElementsByTagName('img')->item(0);
        if (! $image instanceof DOMElement) {
            return null;
        }

        $src = $image->getAttribute('src');
        if ($src === '') {
            return null;
        }

        if (str_starts_with($src, '//')) {
            $src = 'https:'.$src;
        } elseif (str_starts_with($src, '/')) {
            $src = 'https://de.wiki-aventurica.de'.$src;
        }

        return preg_replace('#/\d+px-#', '/250px-', $src, 1) ?? $src;
    }
}
