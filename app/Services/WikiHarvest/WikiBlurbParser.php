<?php

declare(strict_types=1);

namespace App\Services\WikiHarvest;

/**
 * Extrahiert spoilerfreie Kurztexte aus Wiki-Aventurica-Klappentext-HTML.
 */
final class WikiBlurbParser
{
    private const MAX_LENGTH = 1200;

    private const MIN_LENGTH = 20;

    public function extract(string $html): ?string
    {
        if ($html === '') {
            return null;
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return null;
        }

        $xpath = new \DOMXPath($dom);
        $paragraphs = $xpath->query('//div[contains(@class,"mw-parser-output")]//p');
        if ($paragraphs === false || $paragraphs->length === 0) {
            $paragraphs = $xpath->query('//p');
        }

        if ($paragraphs === false) {
            return null;
        }

        $parts = [];
        foreach ($paragraphs as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $text = $this->normalizeText($node->textContent ?? '');
            if ($text === '' || $this->isBoilerplate($text)) {
                continue;
            }

            $parts[] = $text;
            $joined = implode(' ', $parts);
            if (mb_strlen($joined) >= 280) {
                break;
            }
            if (count($parts) >= 3) {
                break;
            }
        }

        if ($parts === []) {
            return null;
        }

        $description = implode(' ', $parts);
        if (mb_strlen($description) > self::MAX_LENGTH) {
            $description = rtrim(mb_substr($description, 0, self::MAX_LENGTH - 1)).'…';
        }

        if (mb_strlen($description) < self::MIN_LENGTH) {
            return null;
        }

        return $description;
    }

    public function klappentextUrl(string $articleUrlOrPath): string
    {
        if (str_starts_with($articleUrlOrPath, 'http://') || str_starts_with($articleUrlOrPath, 'https://')) {
            $base = rtrim($articleUrlOrPath, '/');
        } elseif (str_starts_with($articleUrlOrPath, '/wiki/')) {
            $base = 'https://de.wiki-aventurica.de'.rtrim($articleUrlOrPath, '/');
        } else {
            $page = str_replace(' ', '_', $articleUrlOrPath);
            $base = 'https://de.wiki-aventurica.de/wiki/'.rawurlencode($page);
        }

        if (str_ends_with($base, '/Klappentext')) {
            return $base;
        }

        return $base.'/Klappentext';
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function isBoilerplate(string $text): bool
    {
        $lower = mb_strtolower($text);

        return str_contains($lower, 'andere sprachversionen')
            || str_contains($lower, 'inhaltsverzeichnis')
            || str_contains($lower, 'dieser artikel')
            || str_contains($lower, 'in eigenen worten')
            || str_contains($lower, 'beschreibung ergänzen')
            || str_contains($lower, 'offizielle beschreibung abtippen')
            || preg_match('/^\[\s*\d+\s*\]$/u', $text) === 1;
    }

    /**
     * Fallback: Abschnitt Kurzbeschreibung/Klappentext auf der Hauptseite.
     */
    public function extractFromArticle(string $html): ?string
    {
        $fromKlappentext = $this->extractSectionParagraphs($html, 'Klappentext');
        if ($fromKlappentext !== null) {
            return $fromKlappentext;
        }

        return $this->extractSectionParagraphs($html, 'Kurzbeschreibung');
    }

    private function extractSectionParagraphs(string $html, string $sectionId): ?string
    {
        if ($html === '' || ! str_contains($html, 'id="'.$sectionId.'"')) {
            return null;
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return null;
        }

        $xpath = new \DOMXPath($dom);
        $headingNodes = $xpath->query('//*[@id="'.$sectionId.'"]');
        $heading = $headingNodes !== false ? $headingNodes->item(0) : null;
        if (! $heading instanceof \DOMNode) {
            return null;
        }

        $parts = [];
        for ($node = $heading->parentNode?->nextSibling; $node !== null; $node = $node->nextSibling) {
            if ($node instanceof \DOMElement) {
                $tag = mb_strtolower($node->tagName);
                if (in_array($tag, ['h1', 'h2', 'h3', 'h4'], true) || ($node->getAttribute('class') !== '' && str_contains($node->getAttribute('class'), 'mw-heading'))) {
                    break;
                }
                if ($tag === 'p') {
                    $text = $this->normalizeText($node->textContent ?? '');
                    if ($text !== '' && ! $this->isBoilerplate($text)) {
                        $parts[] = $text;
                    }
                }
            }
            if (count($parts) >= 3) {
                break;
            }
        }

        if ($parts === []) {
            return null;
        }

        $description = implode(' ', $parts);
        if (mb_strlen($description) > self::MAX_LENGTH) {
            $description = rtrim(mb_substr($description, 0, self::MAX_LENGTH - 1)).'…';
        }

        return mb_strlen($description) >= self::MIN_LENGTH ? $description : null;
    }
}
