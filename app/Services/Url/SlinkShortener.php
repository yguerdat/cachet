<?php

namespace App\Services\Url;

use App\Contracts\UrlShortener;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SlinkShortener implements UrlShortener
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $slugPrefix,
        private readonly int $timeout,
    ) {}

    public function shorten(string $longUrl, ?string $slugHint = null): string
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            Log::warning('Slink API key not configured; returning long URL unchanged.');

            return $longUrl;
        }

        $slug = $this->buildSlug($slugHint);

        $response = $this->client()->post('/rest/v3/short-urls', [
            'longUrl' => $longUrl,
            'customSlug' => $slug,
            'findIfExists' => true,
        ]);

        if ($response->status() === 400) {
            return $this->shortenWithoutSlug($longUrl);
        }

        if ($response->failed()) {
            Log::warning('Slink shorten failed; falling back to long URL.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $longUrl;
        }

        return $this->forceHttps((string) ($response->json('shortUrl') ?? $longUrl));
    }

    private function shortenWithoutSlug(string $longUrl): string
    {
        $response = $this->client()->post('/rest/v3/short-urls', [
            'longUrl' => $longUrl,
            'findIfExists' => true,
        ]);

        if ($response->failed()) {
            Log::warning('Slink shorten (no slug) failed; falling back to long URL.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $longUrl;
        }

        return $this->forceHttps((string) ($response->json('shortUrl') ?? $longUrl));
    }

    /**
     * Force HTTPS on the returned short URL when our configured Slink base is
     * already HTTPS. Some Shlink installs default to http:// in their generated
     * short URLs even when the public host serves TLS — we override here so SMS
     * recipients don't get a downgraded link.
     */
    private function forceHttps(string $url): string
    {
        if (! str_starts_with($this->baseUrl, 'https://')) {
            return $url;
        }
        $baseHost = parse_url($this->baseUrl, PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);
        if ($baseHost && $baseHost === $urlHost) {
            return preg_replace('#^http://#i', 'https://', $url, 1) ?? $url;
        }

        return $url;
    }

    private function buildSlug(?string $hint): string
    {
        $token = $hint !== null && $hint !== ''
            ? Str::slug($hint)
            : Str::lower(Str::random(8));

        return $this->slugPrefix.'/'.$token;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'X-Api-Key' => (string) $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->asJson();
    }
}
