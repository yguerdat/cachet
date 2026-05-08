<?php

namespace App\Services\Subscribers;

use App\Contracts\SmsSender;
use Cachet\Models\Subscriber;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

class PhoneVerifier
{
    private const CODE_TTL_SECONDS = 600;

    public function __construct(
        private readonly SmsSender $smsSender,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Generate a one-time code, stash it in the cache and dispatch it via SMS.
     */
    public function sendCode(Subscriber $subscriber): void
    {
        if ($subscriber->phone_number === null || $subscriber->phone_number === '') {
            return;
        }

        $code = $this->generateCode();
        $this->cache->put($this->cacheKey($subscriber), $code, self::CODE_TTL_SECONDS);

        $this->smsSender->send(
            to: $subscriber->phone_number,
            text: __('sms.verify.body', ['code' => $code, 'app' => config('app.name')]),
        );
    }

    /**
     * Verify the supplied code and mark the subscriber's phone as verified
     * if it matches. Returns true on success.
     */
    public function verifyCode(Subscriber $subscriber, string $submittedCode): bool
    {
        $expected = $this->cache->get($this->cacheKey($subscriber));

        if ($expected === null || ! hash_equals((string) $expected, trim($submittedCode))) {
            return false;
        }

        $this->cache->forget($this->cacheKey($subscriber));

        $subscriber->forceFill(['phone_verified_at' => now()])->save();

        return true;
    }

    private function cacheKey(Subscriber $subscriber): string
    {
        return 'subscriber_phone_verify:'.$subscriber->getKey();
    }

    private function generateCode(): string
    {
        return Str::padLeft((string) random_int(0, 999999), 6, '0');
    }
}
