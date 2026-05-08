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

        return (string) ($response->json('shortUrl') ?? $longUrl);
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

        return (string) ($response->json('shortUrl') ?? $longUrl);
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
