<?php

declare(strict_types=1);

use App\Services\WikiHarvest\WikiHttpClient;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

it('returns null when the wiki request times out', function (): void {
    Sleep::fake();
    $url = 'https://de.wiki-aventurica.de/wiki/Zeitueberschreitung';

    Http::preventStrayRequests();
    Http::fake([
        $url => function (): never {
            throw new ConnectionException('cURL error 28: Operation timed out');
        },
    ]);

    $client = new WikiHttpClient(
        cacheDirectory: sys_get_temp_dir().'/wiki-http-client-'.uniqid(),
        pauseMilliseconds: 0,
    );

    expect($client->tryGet($url))->toBeNull();
    Sleep::assertSleptTimes(2);
});

it('returns the page after a dropped connection', function (): void {
    Sleep::fake();
    $url = 'https://de.wiki-aventurica.de/wiki/Abenteuer/VA-Reihe';
    $attempts = 0;

    Http::preventStrayRequests();
    Http::fake([
        $url => function () use (&$attempts) {
            $attempts++;
            if ($attempts === 1) {
                throw new ConnectionException('cURL error 28: Connection timed out');
            }

            return Http::response('<table>index</table>', 200);
        },
    ]);

    $client = new WikiHttpClient(
        cacheDirectory: sys_get_temp_dir().'/wiki-http-client-'.uniqid(),
        pauseMilliseconds: 0,
    );

    expect($client->tryGet($url))->toBe('<table>index</table>')
        ->and($attempts)->toBe(2);
    Sleep::assertSleptTimes(1);
});

it('names the connection error when the wiki stays unreachable', function (): void {
    Sleep::fake();
    $url = 'https://de.wiki-aventurica.de/wiki/Abenteuer/VA-Reihe';

    Http::preventStrayRequests();
    Http::fake([
        $url => function (): never {
            throw new ConnectionException('cURL error 28: Connection timed out');
        },
    ]);

    $client = new WikiHttpClient(
        cacheDirectory: sys_get_temp_dir().'/wiki-http-client-'.uniqid(),
        pauseMilliseconds: 0,
    );

    expect(fn () => $client->get($url))->toThrow(RuntimeException::class, 'cURL error 28: Connection timed out');
});

it('returns null when the wiki responds with an error status', function (): void {
    $url = 'https://de.wiki-aventurica.de/wiki/Fehlt/Klappentext';

    Http::preventStrayRequests();
    Http::fake([
        $url => function (): never {
            throw new RequestException(new Response(new PsrResponse(404, [], 'missing')));
        },
    ]);

    $client = new WikiHttpClient(
        cacheDirectory: sys_get_temp_dir().'/wiki-http-client-'.uniqid(),
        pauseMilliseconds: 0,
    );

    expect($client->tryGet($url))->toBeNull();
});
