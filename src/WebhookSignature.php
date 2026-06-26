<?php

namespace Fastaar;

class WebhookSignature
{
    /**
     * Verify the X-Fastaar-Signature header (`t=<ts>,v1=<hmac>`) against
     * the raw request body using your merchant webhook secret.
     */
    public static function verify(
        string $secret,
        string $rawBody,
        string $signatureHeader,
        int $toleranceSeconds = 300,
    ): bool {
        if (preg_match('/^t=(?<t>\d+),v1=(?<v1>[a-f0-9]{64})$/', $signatureHeader, $matches) !== 1) {
            return false;
        }

        $timestamp = (int) $matches['t'];

        if (abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

        return hash_equals($expected, $matches['v1']);
    }
}
