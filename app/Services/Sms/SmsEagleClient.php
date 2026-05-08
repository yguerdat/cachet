<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Exceptions\SmsDeliveryException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsEagleClient implements SmsSender
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
        private readonly ?string $defaultModem,
        private readonly int $timeout,
    ) {}

    public function send(string $to, string $text): string
    {
        if ($this->token === null || $this->token === '') {
            throw new SmsDeliveryException('SMSeagle token is not configured.');
        }

        $payload = [
            'to' => [$to],
            'text' => $text,
        ];

        if ($this->defaultModem !== null && $this->defaultModem !== '') {
            $payload['modem_no'] = (int) $this->defaultModem;
        }

        $response = $this->client()->post('/api/v2/messages/sms', $payload);

        if ($response->failed()) {
            Log::warning('SMSeagle send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new SmsDeliveryException(sprintf(
                'SMSeagle responded %d: %s',
                $response->status(),
                $response->body() ?: 'no body',
            ));
        }

        return (string) ($response->json('uuid') ?? $response->json('id') ?? '');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'access-token' => (string) $this->token,
                'Accept' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->asJson();
    }
}
