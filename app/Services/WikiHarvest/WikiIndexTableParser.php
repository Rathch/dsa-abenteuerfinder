<?php

declare(strict_types=1);

namespace App\Services\WikiHarvest;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Parst MediaWiki-Wikitable-Indexseiten (VA-/A-Reihe) zu Rohzeilen.
 */
final class WikiIndexTableParser
{
    /**
     * @return list<array{
     *     catalog_number: string,
     *     title: string,
     *     wiki_path: ?string,
     *     rules_raw: string,
     *     place_raw: string,
     *     genre_raw: string,
     *     has_genre_column: bool,
     *     complexity_raw: string,
     *     experience_raw: string,
     *     page_count: ?int,
     *     series_key: string,
     *     series_name: string
     * }>
     */
    public function parse(string $html, string $seriesKey, string $seriesName = '', bool $splitByHeading = false): array
    {
        if ($seriesName === '') {
            $seriesName = $seriesKey;
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $rows = [];

        $tables = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " wikitable ")]');
        if ($tables === false) {
            return [];
        }

        foreach ($tables as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }

            $headerMap = $this->headerMap($table);
            if ($headerMap === null) {
                continue;
            }

            [$rowSeriesKey, $rowSeriesName] = $this->seriesForTable(
                $xpath,
                $table,
                $seriesKey,
                $seriesName,
                $splitByHeading,
            );

            foreach ($table->getElementsByTagName('tr') as $tr) {
                $cells = [];
                foreach ($tr->childNodes as $child) {
                    if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['td', 'th'], true)) {
                        $cells[] = $child;
                    }
                }

                if ($cells === [] || strtolower($cells[0]->tagName) === 'th') {
                    continue;
                }

                $titleCell = $cells[$headerMap['name']] ?? null;
                if ($titleCell === null) {
                    continue;
                }

                $title = $this->cellText($titleCell);
                $rules = isset($headerMap['rules']) ? $this->cellText($cells[$headerMap['rules']] ?? null) : '';
                if ($title === '') {
                    continue;
                }

                $wikiPath = $this->firstWikiPath($titleCell);
                $pages = isset($headerMap['pages']) ? $this->parsePageCount($this->cellText($cells[$headerMap['pages']] ?? null)) : null;

                $rows[] = [
                    'catalog_number' => isset($headerMap['nr']) ? $this->cellText($cells[$headerMap['nr']] ?? null) : '',
                    'title' => $title,
                    'wiki_path' => $wikiPath,
                    'rules_raw' => $rules,
                    'place_raw' => isset($headerMap['place']) ? $this->cellText($cells[$headerMap['place']] ?? null) : '',
                    'genre_raw' => isset($headerMap['genre']) ? $this->cellText($cells[$headerMap['genre']] ?? null) : '',
                    'has_genre_column' => isset($headerMap['genre']),
                    'complexity_raw' => isset($headerMap['complexity']) ? $this->cellText($cells[$headerMap['complexity']] ?? null) : '',
                    'experience_raw' => isset($headerMap['experience']) ? $this->cellText($cells[$headerMap['experience']] ?? null) : '',
                    'page_count' => $pages,
                    'series_key' => $rowSeriesKey,
                    'series_name' => $rowSeriesName,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{nr?: int, name: int, rules?: int, place?: int, genre?: int, complexity?: int, experience?: int, pages?: int}|null
     */
    private function headerMap(DOMElement $table): ?array
    {
        $firstRow = null;
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $firstRow = $tr;
            break;
        }

        if (! $firstRow instanceof DOMElement) {
            return null;
        }

        $headers = [];
        foreach ($firstRow->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['th', 'td'], true)) {
                $headers[] = mb_strtolower($this->cellText($child));
            }
        }

        $name = $this->findHeader($headers, ['name', 'titel', 'abenteuer']);
        if ($name === null) {
            return null;
        }

        $map = ['name' => $name];
        $rules = $this->findHeader($headers, ['regeln', 'regelsystem']);
        if ($rules !== null) {
            $map['rules'] = $rules;
        }
        $nr = $this->findHeader($headers, ['nr', 'nummer']);
        if ($nr !== null) {
            $map['nr'] = $nr;
        }
        $place = $this->findHeader($headers, ['ort', 'schauplatz', 'regionen', 'region']);
        if ($place !== null) {
            $map['place'] = $place;
        }
        $genre = $this->findHeader($headers, ['genre']);
        if ($genre !== null) {
            $map['genre'] = $genre;
        }
        $complexity = $this->findHeader($headers, ['komplexität meister', 'komplexitaet meister', 'komplexität (meister)']);
        if ($complexity !== null) {
            $map['complexity'] = $complexity;
        }
        $experience = $this->findHeader($headers, ['erfahrung', 'erfahrung helden']);
        if ($experience !== null) {
            $map['experience'] = $experience;
        }
        $pages = $this->findHeader($headers, ['seiten', 'seitenzahl']);
        if ($pages !== null) {
            $map['pages'] = $pages;
        }

        return $map;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function seriesForTable(
        DOMXPath $xpath,
        DOMElement $table,
        string $fallbackKey,
        string $fallbackName,
        bool $splitByHeading,
    ): array {
        if (! $splitByHeading) {
            return [$fallbackKey, $fallbackName];
        }

        $inArticle = $xpath->query('ancestor::*[contains(concat(" ", normalize-space(@class), " "), " mw-parser-output ")]', $table);
        $articleHeading = 'preceding::*[(self::h2 or self::h3) and ancestor::*[contains(concat(" ", normalize-space(@class), " "), " mw-parser-output ")]][1]';
        $headlines = $xpath->query(
            $inArticle !== false && $inArticle->length > 0
                ? $articleHeading
                : 'preceding::*[self::h2 or self::h3][1]',
            $table,
        );
        $headline = $headlines !== false ? $headlines->item(0) : null;
        $name = $headline instanceof DOMElement ? $this->headingText($headline) : '';
        if ($name === '' || $this->isNavigationHeading($name)) {
            return [$fallbackKey, $fallbackName];
        }

        $key = Str::slug($name, language: 'de');

        return [$key !== '' ? $key : $fallbackKey, $name];
    }

    private function headingText(DOMElement $headline): string
    {
        $clone = $headline->cloneNode(true);
        if (! $clone instanceof DOMElement) {
            return trim($headline->textContent ?? '');
        }

        $edits = [];
        foreach ($clone->getElementsByTagName('span') as $span) {
            if (str_contains(' '.$span->getAttribute('class').' ', ' mw-editsection ')) {
                $edits[] = $span;
            }
        }
        foreach ($edits as $span) {
            $span->parentNode?->removeChild($span);
        }

        $text = preg_replace('/\s+/u', ' ', $clone->textContent ?? '') ?? '';

        return trim($text);
    }

    private function isNavigationHeading(string $name): bool
    {
        $normalized = mb_strtolower($name);

        return in_array($normalized, [
            'inhaltsverzeichnis',
            'navigation',
            'siehe auch',
            'erklaerung',
            'erklärung',
            'weblinks',
        ], true);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $needles
     */
    private function findHeader(array $headers, array $needles): ?int
    {
        foreach ($headers as $index => $header) {
            foreach ($needles as $needle) {
                if ($header === $needle || str_contains($header, $needle)) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function cellText(?DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }

        $text = $node->textContent ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function firstWikiPath(DOMElement $cell): ?string
    {
        foreach ($cell->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            if (str_starts_with($href, '/wiki/')) {
                return $href;
            }
            if (preg_match('#https?://de\.wiki-aventurica\.de(/wiki/.+)$#', $href, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function parsePageCount(?string $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (preg_match('/(\d{1,4})/', $raw, $matches) !== 1) {
            return null;
        }

        $pages = (int) $matches[1];

        return $pages > 0 ? $pages : null;
    }
}
