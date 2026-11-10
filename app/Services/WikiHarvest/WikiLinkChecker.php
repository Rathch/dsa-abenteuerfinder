<?php

declare(strict_types=1);

namespace App\Services\WikiHarvest;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Prüft Wiki-Aventurica-Artikeladressen über die API. Eine Weiterleitung zählt als vorhanden.
 */
final class WikiLinkChecker
{
    private const API = 'https://de.wiki-aventurica.de/de/api.php';

    private const USER_AGENT = 'DSA-Abenteuerfinder/1.0 (+https://github.com/rathch/dsa-abenteuerfinder; wiki-harvest; contact: local-dev)';

    private const BATCH_SIZE = 40;

    /**
     * @param  list<string>  $urls
     * @return list<string> Adressen, deren Seite fehlt oder deren Form keine Wiki-Artikeladresse ist
     */
    public function missingPages(array $urls): array
    {
        /** @var array<string, string> $titles url => Seitentitel */
        $titles = [];
        $missing = [];

        foreach ($urls as $url) {
            $title = $this->titleFromUrl($url);
            if ($title === null) {
                $missing[] = $url;

                continue;
            }

            $titles[$url] = $title;
        }

        $piped = [];
        $safe = [];
        foreach ($titles as $url => $title) {
            if (str_contains($title, '|')) {
                $piped[$url] = $title;
            } else {
                $safe[$url] = $title;
            }
        }

        foreach ($piped as $url => $title) {
            $missing = array_merge($missing, $this->missingInBatch([$url => $title]));
        }

        foreach (array_chunk($safe, self::BATCH_SIZE, true) as $batch) {
            $missing = array_merge($missing, $this->missingInBatch($batch));
        }

        return $missing;
    }

    /**
     * @param  array<string, string>  $batch  url => Seitentitel
     * @return list<string>
     */
    private function missingInBatch(array $batch): array
    {
        $payload = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/json',
        ])
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(3, 0, function (\Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->status() === 429));
            })
            ->asForm()
            ->post(self::API, [
                'action' => 'query',
                'format' => 'json',
                'redirects' => '1',
                'titles' => implode('|', array_values($batch)),
            ])
            ->throw()
            ->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Wiki-API lieferte kein JSON-Objekt.');
        }

        if (isset($payload['error'])) {
            $info = is_array($payload['error']) ? (string) ($payload['error']['info'] ?? 'unbekannter Fehler') : 'unbekannter Fehler';

            throw new RuntimeException('Wiki-API-Fehler: '.$info);
        }

        $query = $payload['query'] ?? null;
        if (! is_array($query)) {
            throw new RuntimeException('Wiki-API-Antwort ohne query.');
        }

        /** @var array<string, string> $normalized */
        $normalized = [];
        foreach ($this->rows($query['normalized'] ?? null) as $row) {
            $normalized[$this->canon((string) $row['from'])] = (string) $row['to'];
        }

        /** @var array<string, string> $redirects */
        $redirects = [];
        foreach ($this->rows($query['redirects'] ?? null) as $row) {
            $redirects[$this->canon((string) $row['from'])] = (string) $row['to'];
        }

        /** @var array<string, array<string, mixed>> $pages */
        $pages = [];
        $pageRows = $query['pages'] ?? null;
        if (is_array($pageRows)) {
            foreach ($pageRows as $page) {
                if (! is_array($page) || ! isset($page['title']) || ! is_string($page['title'])) {
                    continue;
                }
                $pages[$this->canon($page['title'])] = $page;
            }
        }

        $missing = [];
        foreach ($batch as $url => $title) {
            $resolved = $this->resolveTitle($title, $normalized, $redirects);
            $page = $pages[$this->canon($resolved)] ?? null;
            if ($page === null || isset($page['missing']) || isset($page['invalid'])) {
                $missing[] = $url;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, string>  $normalized
     * @param  array<string, string>  $redirects
     */
    private function resolveTitle(string $title, array $normalized, array $redirects): string
    {
        $current = $title;
        $seen = [];

        for ($step = 0; $step < 6; $step++) {
            $key = $this->canon($current);
            if (isset($seen[$key])) {
                break;
            }
            $seen[$key] = true;

            if (isset($normalized[$key])) {
                $current = $normalized[$key];

                continue;
            }

            if (isset($redirects[$key])) {
                $current = $redirects[$key];

                continue;
            }

            break;
        }

        return $current;
    }

    /**
     * @return list<array{from: string, to: string}>
     */
    private function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row) || ! isset($row['from'], $row['to']) || ! is_string($row['from']) || ! is_string($row['to'])) {
                continue;
            }
            $rows[] = ['from' => $row['from'], 'to' => $row['to']];
        }

        return $rows;
    }

    private function titleFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['host'] ?? '') !== 'de.wiki-aventurica.de') {
            return null;
        }

        $path = $parts['path'] ?? '';
        if (! str_starts_with($path, '/wiki/') || $path === '/wiki/') {
            return null;
        }

        $title = rawurldecode(substr($path, strlen('/wiki/')));
        $title = str_replace('_', ' ', $title);
        if ($title === '') {
            return null;
        }

        return $title;
    }

    private function canon(string $title): string
    {
        return mb_strtolower(str_replace('_', ' ', $title));
    }
}
