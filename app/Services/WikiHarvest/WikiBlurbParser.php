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
        $text = str_replace("\u{200b}", '', $text);
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
            || str_contains($lower, 'beschreibung des abenteuers')
            || str_contains($lower, 'steht noch aus')
            || preg_match('/\/klappentext\s*$/u', $lower) === 1
            || preg_match('/^\[\s*\d+\s*\]$/u', $text) === 1;
    }

    /**
     * Offizieller Verlagstext aus dem Abschnitt Klappentext.
     */
    public function klappentextFromArticle(string $html): ?string
    {
        return $this->extractSectionParagraphs($html, 'Klappentext');
    }

    /**
     * Wiki-Zusammenfassung aus dem Abschnitt Kurzbeschreibung.
     */
    public function kurzbeschreibungFromArticle(string $html): ?string
    {
        return $this->extractSectionParagraphs($html, 'Kurzbeschreibung');
    }

    /**
     * Klappentext, und nur ohne diesen Abschnitt die Kurzbeschreibung.
     */
    public function extractFromArticle(string $html): ?string
    {
        return $this->klappentextFromArticle($html) ?? $this->kurzbeschreibungFromArticle($html);
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
        for ($node = $this->headingContainer($heading)->nextSibling; $node !== null; $node = $node->nextSibling) {
            if ($this->isNextSection($node)) {
                break;
            }

            foreach ($this->collectTextBlocks($node) as $text) {
                if ($text === '' || $this->isBoilerplate($text) || $this->isAttribution($text)) {
                    continue;
                }

                $parts[] = $text;
                $joined = implode(' ', $parts);
                if (count($parts) >= 3 || mb_strlen($joined) >= 280) {
                    break 2;
                }
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

    private function headingContainer(\DOMNode $heading): \DOMNode
    {
        $block = $heading;
        while ($block->parentNode instanceof \DOMElement) {
            $parent = $block->parentNode;
            $tag = strtolower($parent->tagName);
            $class = $parent->getAttribute('class');
            if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) || str_contains($class, 'mw-heading')) {
                $block = $parent;

                continue;
            }

            break;
        }

        return $block;
    }

    private function isNextSection(\DOMNode $node): bool
    {
        if (! $node instanceof \DOMElement) {
            return false;
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, ['h1', 'h2', 'h3', 'h4'], true)) {
            return true;
        }

        return str_contains($node->getAttribute('class'), 'mw-heading');
    }

    /**
     * @return list<string>
     */
    private function collectTextBlocks(\DOMNode $node): array
    {
        if (! $node instanceof \DOMElement) {
            return [];
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style'], true)) {
            return [];
        }

        if ($tag === 'p') {
            $text = $this->normalizeText($node->textContent ?? '');

            return $text === '' ? [] : [$text];
        }

        if (in_array($tag, ['td', 'th', 'li'], true)) {
            return $this->blocksFromMixedContent($node);
        }

        $blocks = [];
        foreach ($node->childNodes as $child) {
            if ($this->isNextSection($child)) {
                break;
            }

            $blocks = array_merge($blocks, $this->collectTextBlocks($child));
        }

        if ($blocks === []) {
            $text = $this->normalizeText($node->textContent ?? '');

            return $text === '' ? [] : [$text];
        }

        return $blocks;
    }

    /**
     * @return list<string>
     */
    private function blocksFromMixedContent(\DOMElement $node): array
    {
        $blocks = [];
        $buffer = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'p') {
                $lead = $this->normalizeText($buffer);
                if ($lead !== '') {
                    $blocks[] = $lead;
                }
                $buffer = '';
                $text = $this->normalizeText($child->textContent ?? '');
                if ($text !== '') {
                    $blocks[] = $text;
                }

                continue;
            }

            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'br') {
                $lead = $this->normalizeText($buffer);
                if ($lead !== '') {
                    $blocks[] = $lead;
                }
                $buffer = '';

                continue;
            }

            $buffer .= $child->textContent ?? '';
        }

        $tail = $this->normalizeText($buffer);
        if ($tail !== '') {
            $blocks[] = $tail;
        }

        return $blocks;
    }

    private function isAttribution(string $text): bool
    {
        $trimmed = ltrim($text);
        $lower = mb_strtolower($trimmed);

        return str_starts_with($trimmed, '—')
            || str_starts_with($trimmed, '–')
            || str_contains($lower, 'zur weiterverwendung')
            || str_contains($lower, 'klappentext von');
    }
}
