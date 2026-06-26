<?php

namespace Fastaar;

class FastaarClient
{
    private const BASE_URL = 'https://fastaar.com';

    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 15,
    ) {}

    /**
     * Create a payment intent.
     *
     * Reusing the same `invoice_id` returns the existing payment instead of
     * creating a duplicate (HTTP 200 rather than 201), so retries are safe.
     * Supply `success_url`/`cancel_url` to return the customer to your site
     * after checkout; Fastaar appends `payment_id` (and `invoice_id`) to them.
     *
     * @param  array{
     *     amount: int|float|string,
     *     invoice_id?: string,
     *     success_url?: string,
     *     cancel_url?: string,
     *     metadata?: array<string, string>
     * }  $params
     * @return array<string, mixed> The payment object, including `id`, `status`, and `checkout_url`.
     */
    public function createPayment(array $params): array
    {
        return $this->request('POST', '/api/v1/payments', $params);
    }

    /**
     * Retrieve a payment by its reference (the `id` returned at creation).
     *
     * @return array<string, mixed>
     */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/api/v1/payments/'.rawurlencode($paymentId));
    }

    /**
     * List payments, newest first.
     *
     * @param  array{status?: string, invoice_id?: string, per_page?: int, page?: int}  $params
     * @return array<int, array<string, mixed>>
     */
    public function listPayments(array $params = []): array
    {
        $query = $params === [] ? '' : '?'.http_build_query($params);

        return $this->request('GET', '/api/v1/payments'.$query);
    }

    /**
     * Find the most recent payment for one of your invoice IDs, or null if none.
     *
     * @return array<string, mixed>|null
     */
    public function findByInvoiceId(string $invoiceId): ?array
    {
        $payments = $this->listPayments(['invoice_id' => $invoiceId]);

        return $payments[0] ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws FastaarException
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $handle = curl_init(self::BASE_URL.$path);

        $headers = [
            'Authorization: Bearer '.$this->apiKey,
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($handle, $options);

        $response = curl_exec($handle);

        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new FastaarException("Could not reach the Fastaar API: {$error}", 'connection_error');
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        $decoded = json_decode($response, true);

        if ($statusCode >= 400 || ! is_array($decoded)) {
            throw new FastaarException(
                $decoded['error']['message'] ?? "Fastaar API returned HTTP {$statusCode}.",
                $decoded['error']['type'] ?? 'api_error',
                $statusCode,
            );
        }

        return $decoded['data'] ?? $decoded;
    }
}
