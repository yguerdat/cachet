<?php

namespace App\Contracts;

interface SmsSender
{
    /**
     * Send an SMS to a single recipient. Phone numbers must be in E.164 format.
     *
     * Returns the gateway message id on success, or throws on transport failure.
     */
    public function send(string $to, string $text): string;
}
