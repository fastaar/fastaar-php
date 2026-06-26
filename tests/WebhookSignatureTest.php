<?php

namespace Fastaar\Tests;

use Fastaar\WebhookSignature;
use PHPUnit\Framework\TestCase;

class WebhookSignatureTest extends TestCase
{
    private function sign(string $secret, string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},v1={$signature}";
    }

    public function test_verify_accepts_valid_signature(): void
    {
        $secret = 'whsec_test_secret';
        $payload = json_encode(['event' => 'payment.completed', 'data' => ['id' => '01TEST']]);
        $header = $this->sign($secret, $payload);

        $this->assertTrue(WebhookSignature::verify($secret, $payload, $header));
    }

    public function test_verify_rejects_wrong_secret(): void
    {
        $secret = 'whsec_test_secret';
        $payload = json_encode(['event' => 'payment.completed', 'data' => ['id' => '01TEST']]);
        $header = $this->sign($secret, $payload);

        $this->assertFalse(WebhookSignature::verify('wrong-secret', $payload, $header));
    }

    public function test_verify_rejects_tampered_payload(): void
    {
        $secret = 'whsec_test_secret';
        $payload = json_encode(['event' => 'payment.completed', 'data' => ['id' => '01TEST']]);
        $header = $this->sign($secret, $payload);

        $this->assertFalse(WebhookSignature::verify($secret, '{"tampered":true}', $header));
    }

    public function test_verify_rejects_expired_timestamp(): void
    {
        $secret = 'whsec_test_secret';
        $payload = json_encode(['event' => 'payment.completed']);
        $header = $this->sign($secret, $payload, time() - 400);

        $this->assertFalse(WebhookSignature::verify($secret, $payload, $header, 300));
    }

    public function test_verify_rejects_malformed_header(): void
    {
        $this->assertFalse(WebhookSignature::verify('secret', 'payload', 'not-a-valid-header'));
        $this->assertFalse(WebhookSignature::verify('secret', 'payload', ''));
    }
}
