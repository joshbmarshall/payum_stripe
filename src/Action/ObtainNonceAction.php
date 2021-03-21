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

    /**
     * @param string $templateName
     */
    public function __construct(string $templateName) {
        $this->templateName = $templateName;
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

        $getHttpRequest = new GetHttpRequest();
        $this->gateway->execute($getHttpRequest);
        if ($getHttpRequest->method == 'POST' && isset($getHttpRequest->request['payment_intent'])) {
            $model['nonce'] = $getHttpRequest->request['payment_intent'];

            return;
        }

        if (false) {
            $email = $model['local']['email'];

            // Search for customer
            $customer_id = md5($email);
            try {
                $customer = $model['stripeElementsGateway']->customer()->find($customer_id);
            } catch (\Exception $e) {
                $result = $model['stripeElementsGateway']->customer()->create([
                    'id' => $customer_id,
                    'email' => $email,
                ]);
            }
            //dump($StripeElementsGateway->clientToken()->generate());exit;
            $clientToken = $model['stripeElementsGateway']->clientToken()->generate([
                'customerId' => $customer_id,
            ]);
        }

        $model['stripePaymentIntent'] = \Stripe\PaymentIntent::create([
            'amount' => round($model['amount'] * 100),
            'currency' => $model['currency'],
            // Verify your integration in this guide by including this parameter
            'metadata' => ['integration_check' => 'accept_a_payment'],
        ]);

        $this->gateway->execute($renderTemplate = new RenderTemplate($this->templateName, array(
            //'model' => $model,
            'amount' => $model['currencySymbol'] . ' ' . number_format($model['amount'], $model['currencyDigits']),
            //'clientToken' => $clientToken,
            'client_secret' => $model['stripePaymentIntent']->client_secret,
            'publishable_key' => $model['publishable_key'],
            'actionUrl' => $getHttpRequest->uri,
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
