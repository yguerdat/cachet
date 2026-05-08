<?php

namespace App\Contracts;

interface UrlShortener
{
    /**
     * Shorten the given long URL. The shortener may use the provided slug as
     * a hint; if the slug is already taken, the implementation falls back to
     * a random one.
     */
    public function shorten(string $longUrl, ?string $slugHint = null): string;
}
