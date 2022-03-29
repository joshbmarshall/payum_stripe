<?php

namespace Cognito\PayumStripeElements\Action;

use Cognito\PayumStripeElements\Request\Api\ObtainNonce;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\LogicException;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\RenderTemplate;

class ObtainNonceAction implements ActionInterface, GatewayAwareInterface {
    use GatewayAwareTrait;


    /**
     * @var string
     */
    protected $templateName;
    protected $use_afterpay;

    /**
     * @param string $templateName
     */
    public function __construct(string $templateName, bool $use_afterpay) {
        $this->templateName = $templateName;
        $this->use_afterpay = $use_afterpay;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request) {
        /** @var $request ObtainNonce */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if ($model['card']) {
            throw new LogicException('The token has already been set.');
        }
        $uri = \League\Uri\Http::createFromServer($_SERVER);

        $getHttpRequest = new GetHttpRequest();
        $this->gateway->execute($getHttpRequest);
        // Received payment intent information from Stripe
        if (isset($getHttpRequest->request['payment_intent'])) {
            $model['nonce'] = $getHttpRequest->request['payment_intent'];
            return;
        }
        $afterPayDetails = [];
        if ($this->use_afterpay) { // Afterpay order
            $afterPayDetails = [
                'confirm' => true,
                'payment_method_types' => ['afterpay_clearpay'],
                'shipping' => $model['shipping'],
                'payment_method_data' => [
                    'type' => 'afterpay_clearpay',
                    'billing_details' => $model['billing'],
                ],
                'return_url' => $uri->withPath('')->withFragment('')->withQuery('')->__toString() . $getHttpRequest->uri,
            ];
        }
        $paymentIntentData = array_merge([
            'amount' => round($model['amount'] * pow(10, $model['currencyDigits'])),
            'payment_method_types' => $model['payment_method_types'] ?? ['card'],
            'currency' => $model['currency'],
            'metadata' => ['integration_check' => 'accept_a_payment'],
            'statement_descriptor' => $model['statement_descriptor_suffix'],
            'description' => $model['description'],
        ], $afterPayDetails);

        $model['stripePaymentIntent'] = \Stripe\PaymentIntent::create($paymentIntentData);
        $this->gateway->execute($renderTemplate = new RenderTemplate($this->templateName, array(
            'amount' => $model['currencySymbol'] . ' ' . number_format($model['amount'], $model['currencyDigits']),
            'client_secret' => $model['stripePaymentIntent']->client_secret,
            'publishable_key' => $model['publishable_key'],
            'actionUrl' => $getHttpRequest->uri,
            'imgUrl' => $model['img_url'],
            'use_afterpay' => $this->use_afterpay ? "true" : "false",
            'billing' => $model['billing'] ?? [],
        )));

        throw new HttpResponse($renderTemplate->getResult());
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request) {
        return
            $request instanceof ObtainNonce &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
