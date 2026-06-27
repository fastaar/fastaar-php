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
    'invoice_number' => 'ORDER-42',                         // required — your order reference
    'success_url' => 'https://shop.example.com/thanks', // optional, customer returns here
    'cancel_url' => 'https://shop.example.com/cart',    // optional
]);

header('Location: '.$payment['checkout_url']);
exit;
```

`invoice_number` is idempotent: retrying with the same value returns the existing payment
instead of creating a duplicate, so a dropped connection never double-charges.

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
    $orderId = $event['data']['invoice_number'];
    // mark the order as paid, idempotently (use $event['data']['id'] as the key)
}

http_response_code(200);
```

## Other payment calls

```php
$payment  = $fastaar->getPayment('01jxyz...');                   // retrieve one
$payment  = $fastaar->findByInvoiceNumber('ORDER-42');            // look up by your reference
$payments = $fastaar->listPayments(['status' => 'completed']);
$payment  = $fastaar->refundPayment('01jxyz...');                 // refund a completed payment
```

## Customers

Store customer records against your Fastaar account to attach them to payments collected via payment links.

```php
// Create a customer — name and phone are required
$customer = $fastaar->createCustomer([
    'name'    => 'Rahim Uddin',
    'phone'   => '01712345678',
    'email'   => 'rahim@example.com',   // optional
    'address' => 'Dhaka, Bangladesh',   // optional
    'notes'   => 'VIP customer',        // optional
]);

// Retrieve, update, list
$customer  = $fastaar->getCustomer($customer['id']);
$customer  = $fastaar->updateCustomer($customer['id'], ['name' => 'Rahim Ahmed']);
$customers = $fastaar->listCustomers(['email' => 'rahim@example.com']);
```

Errors throw `Fastaar\FastaarException` with `->errorType` (e.g. `authentication_error`,
`subscription_required`, `transaction_limit_reached`) and `->statusCode`.

## Test mode

Use an `fk_test_` key: payments auto-complete on the checkout page without real money,
and webhooks fire exactly like production with `"livemode": false`.
