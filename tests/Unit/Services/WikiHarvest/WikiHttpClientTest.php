<?php

declare(strict_types=1);

use App\Services\WikiHarvest\WikiHttpClient;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

it('returns null when the wiki request times out', function (): void {
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
