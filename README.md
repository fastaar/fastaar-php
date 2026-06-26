# Fastaar PHP SDK

Accept bKash & Nagad payments on any PHP website via [Fastaar](https://fastaar.com).

## Install

```bash
composer require fastaar/fastaar-php
```

## Create a payment & redirect to checkout

```php
use Fastaar\FastaarClient;

$fastaar = new FastaarClient(apiKey: getenv('FASTAAR_API_KEY')); // fk_live_... or fk_test_...

$payment = $fastaar->createPayment([
    'amount' => 1250,
    'invoice_id' => 'ORDER-42',                         // your order reference
    'success_url' => 'https://shop.example.com/thanks', // optional, customer returns here
    'cancel_url' => 'https://shop.example.com/cart',    // optional
]);

header('Location: '.$payment['checkout_url']);
exit;
```

Passing the same `invoice_id` again returns the existing payment instead of
creating a duplicate, so a retried request never double-charges.

## Confirm the order from a webhook

```php
use Fastaar\WebhookSignature;

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_FASTAAR_SIGNATURE'] ?? '';

if (! WebhookSignature::verify(getenv('FASTAAR_WEBHOOK_SECRET'), $rawBody, $signature)) {
    http_response_code(400);
    exit;
}

$event = json_decode($rawBody, true);

if ($event['event'] === 'payment.completed') {
    $orderId = $event['data']['invoice_id'];
    // mark the order as paid, idempotently (use $event['data']['id'] as the key)
}

http_response_code(200);
```

## Other calls

```php
$payment = $fastaar->getPayment('01jxyz...');                 // retrieve one
$payment = $fastaar->findByInvoiceId('ORDER-42');             // look up by your reference
$payments = $fastaar->listPayments(['status' => 'completed']);
```

Errors throw `Fastaar\FastaarException` with `->errorType` (e.g. `authentication_error`,
`subscription_required`, `transaction_limit_reached`) and `->statusCode`.

## Test mode

Use an `fk_test_` key: payments auto-complete on the checkout page without real money,
and webhooks fire exactly like production with `"livemode": false`.
