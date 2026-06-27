<?php

namespace Fastaar;

class FastaarClient
{
    private const BASE_URL = 'https://fastaar.com';

    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 15,
    ) {}

    // -------------------------------------------------------------------------
    // Payments
    // -------------------------------------------------------------------------

    /**
     * Create a payment intent.
     *
     * Reusing the same `invoice_number` returns the existing payment instead of
     * creating a duplicate (HTTP 200 rather than 201), so retries are safe.
     * Supply `success_url`/`cancel_url` to return the customer to your site
     * after checkout; Fastaar appends `payment_id` (and `invoice_number`) to them.
     *
     * @param  array{
     *     amount: int|float|string,
     *     invoice_number: string,
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
     * @param  array{status?: string, invoice_number?: string, per_page?: int, page?: int}  $params
     * @return array<int, array<string, mixed>>
     */
    public function listPayments(array $params = []): array
    {
        $query = $params === [] ? '' : '?'.http_build_query($params);

        return $this->request('GET', '/api/v1/payments'.$query);
    }

    /**
     * Find the most recent payment for one of your invoice numbers, or null if none.
     *
     * @return array<string, mixed>|null
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?array
    {
        $payments = $this->listPayments(['invoice_number' => $invoiceNumber]);

        return $payments[0] ?? null;
    }

    /**
     * Refund a completed payment. Only payments with status `completed` can be refunded.
     *
     * @return array<string, mixed> The updated payment object with status `refunded`.
     *
     * @throws FastaarException if the payment is not in a refundable state.
     */
    public function refundPayment(string $paymentId): array
    {
        return $this->request('POST', '/api/v1/payments/'.rawurlencode($paymentId).'/refund');
    }

    // -------------------------------------------------------------------------
    // Customers
    // -------------------------------------------------------------------------

    /**
     * List customers, newest first.
     *
     * @param  array{email?: string, phone?: string, per_page?: int, page?: int}  $params
     * @return array<int, array<string, mixed>>
     */
    public function listCustomers(array $params = []): array
    {
        $query = $params === [] ? '' : '?'.http_build_query($params);

        return $this->request('GET', '/api/v1/customers'.$query);
    }

    /**
     * Create a customer.
     *
     * @param  array{name: string, phone: string, email?: string, address?: string, notes?: string}  $params
     * @return array<string, mixed>
     */
    public function createCustomer(array $params): array
    {
        return $this->request('POST', '/api/v1/customers', $params);
    }

    /**
     * Retrieve a customer by ID.
     *
     * @return array<string, mixed>
     */
    public function getCustomer(int $customerId): array
    {
        return $this->request('GET', '/api/v1/customers/'.$customerId);
    }

    /**
     * Update a customer (partial update — only the fields you send are changed).
     *
     * @param  array{name?: string, email?: string|null, phone?: string|null, address?: string|null, notes?: string|null}  $params
     * @return array<string, mixed>
     */
    public function updateCustomer(int $customerId, array $params): array
    {
        return $this->request('PATCH', '/api/v1/customers/'.$customerId, $params);
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
            throw new FastaarException('Could not reach the Fastaar API: '.curl_error($handle), 'connection_error');
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        $decoded = json_decode($response, true);

        if ($statusCode >= 400 || ! is_array($decoded)) {
            throw new FastaarException(
                $decoded['message'] ?? "Fastaar API returned HTTP {$statusCode}.",
                $decoded['code'] ?? 'api_error',
                $statusCode,
            );
        }

        return $decoded['data'] ?? $decoded;
    }
}
