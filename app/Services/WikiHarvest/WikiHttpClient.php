<?php

declare(strict_types=1);

namespace App\Services\WikiHarvest;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Höflicher HTTP-Client für Wiki-Aventurica-Indexseiten mit Datei-Cache.
 */
final class WikiHttpClient
{
    private const USER_AGENT = 'DSA-Abenteuerfinder/1.0 (+https://github.com/rathch/dsa-abenteuerfinder; wiki-harvest; contact: local-dev)';

    public function __construct(
        private readonly string $cacheDirectory,
        private readonly int $pauseMilliseconds = 1000,
        private readonly bool $fromCacheOnly = false,
    ) {}

    public function get(string $url): string
    {
        $body = $this->tryGet($url);
        if ($body === null) {
            if ($this->fromCacheOnly) {
                throw new RuntimeException('Wiki-Cache fehlt für '.$url.' (Flag --from-cache).');
            }

            throw new RuntimeException('Wiki-Abruf fehlgeschlagen: '.$url);
        }

        return $body;
    }

    /**
     * Wie get(), liefert aber null bei fehlendem Cache (--from-cache) oder HTTP-Fehler.
     */
    public function tryGet(string $url): ?string
    {
        $cachePath = $this->cachePathFor($url);

        if (is_file($cachePath)) {
            $cached = file_get_contents($cachePath);
            if ($cached !== false && $cached !== '') {
                return $cached;
            }
        }

        if ($this->fromCacheOnly) {
            return null;
        }

        if (is_dir($this->cacheDirectory) === false) {
            mkdir($this->cacheDirectory, 0775, true);
        }

        usleep($this->pauseMilliseconds * 1000);

        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'text/html',
        ])->timeout(30)->get($url);

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        file_put_contents($cachePath, $body);

        return $body;
    }

    public function warmFromFixture(string $url, string $html): void
    {
        if (is_dir($this->cacheDirectory) === false) {
            mkdir($this->cacheDirectory, 0775, true);
        }

        file_put_contents($this->cachePathFor($url), $html);
    }

    private function cachePathFor(string $url): string
    {
        return rtrim($this->cacheDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.hash('sha256', $url).'.html';
    }
}
