# Stripe Elements Payment Module

The Payum extension to purchase through Stripe using Elements

## Install and Use

To install, it's easiest to use composer:

    composer require cognito/payum_stripe_elements

### Build the config

```php
<?php

use Payum\Core\PayumBuilder;
use Payum\Core\GatewayFactoryInterface;

$defaultConfig = [];

$payum = (new PayumBuilder)
    ->addGatewayFactory('stripe_elements', function(array $config, GatewayFactoryInterface $coreGatewayFactory) {
        return new \Cognito\PayumStripeElements\StripeElementsGatewayFactory($config, $coreGatewayFactory);
    })

    ->addGateway('stripe_elements', [
        'factory' => 'stripe_elements',
        'publishable_key' => 'Your Public Key',
        'secret_key' => 'Your Private Key',
        'img_url' => 'https://path/to/logo/image.jpg',
        'img_2_url' => 'https://path/to/logo/pay_by_image.jpg',
    ])

    ->getPayum()
;
```

### Request card payment

```php
<?php

use Payum\Core\Request\Capture;

$storage = $payum->getStorage(\Payum\Core\Model\Payment::class);
$request = [
    'invoice_id' => 100,
];

$payment = $storage->create();
$payment->setNumber(uniqid());
$payment->setCurrencyCode($currency);
$payment->setTotalAmount(100); // Total cents
$payment->setDescription(substr($description, 0, 45));
$payment->setDetails([
    'local' => [
        'email' => $email, // Used for the customer to be able to save payment details
    ],
    'limit_payment_type' => 'card', // Optionally limit to card transactions only, to disable Afterpay / Klarna / Zip etc.
    'payment_method_options' => [
        'layout' => 'tabs',
        'paymentMethodOrder' =>
        [
            'apple_pay',
            'google_pay',
            'card',
        ], // Optionally re-order the wallets to be before card if they apply to the transaction
        'defaultValues' => [
            'billingDetails' => [
                'name' => $customer_name,
                'email' => $customer_email,
            ], // Optionally prefill some fields for some payment types
        ],
    ],
]);
$storage->setInternalDetails($payment, $request);

$captureToken = $payum->getTokenFactory()->createCaptureToken('stripe_elements', $payment, 'done.php');
$url = $captureToken->getTargetUrl();
header("Location: " . $url);
die();
```

### Request Afterpay, Klarna, Affirm, Zip etc payment

BNPL requires more information about the customer to process the payment

```php
<?php

use Payum\Core\Request\Capture;

$storage = $payum->getStorage(\Payum\Core\Model\Payment::class);
$request = [
    'invoice_id' => 100,
];

$payment = $storage->create();
$payment->setNumber(uniqid());
$payment->setCurrencyCode($currency);
$payment->setTotalAmount(100); // Total cents
$payment->setDescription(substr($description, 0, 45));
$payment->setDetails([
    'local' => [
        'email' => $email, // Used for the customer to be able to save payment details
    ],
    'limit_payment_type' => 'afterpay_clearpay,klarna,affirm,zip', // List of payment types at https://stripe.com/docs/api/payment_methods/object#payment_method_object-type
    'shipping' => [
        'name' => 'Firstname Lastname',
        'address' => [
            'line1' => 'Address Line 1',
            'city' => 'Address City',
            'state' => 'Address State',
            'country' => 'Address Country',
            'postal_code' => 'Address Postal Code',
        ],
    ],
    'billing' => [
        'name' => trim($shopper['first_name'] . ' ' . $shopper['last_name']),
        'email' => $shopper['email'],
        'address' => [
            'line1' => 'Address Line 1',
            'city' => 'Address City',
            'state' => 'Address State',
            'country' => 'Address Country',
            'postal_code' => 'Address Postal Code',
        ],
    ],
]);
$storage->setInternalDetails($payment, $request);

$captureToken = $payum->getTokenFactory()->createCaptureToken('stripe_elements', $payment, 'done.php');
$url = $captureToken->getTargetUrl();
header("Location: " . $url);
die();
```

### Check it worked

```php
<?php
/** @var \Payum\Core\Model\Token $token */
$token = $payum->getHttpRequestVerifier()->verify($request);
$gateway = $payum->getGateway($token->getGatewayName());

/** @var \Payum\Core\Storage\IdentityInterface $identity **/
$identity = $token->getDetails();
$model = $payum->getStorage($identity->getClass())->find($identity);
$gateway->execute($status = new GetHumanStatus($model));

/** @var \Payum\Core\Request\GetHumanStatus $status */

// using shortcut
if ($status->isNew() || $status->isCaptured() || $status->isAuthorized()) {
    // success
} elseif ($status->isPending()) {
    // most likely success, but you have to wait for a push notification.
} elseif ($status->isFailed() || $status->isCanceled()) {
    // the payment has failed or user canceled it.
}
```

## License

Payum Stripe Elements is released under the [MIT License](LICENSE).
