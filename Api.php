<?php

namespace Omnitrade\Stripe;

use Omnipay\Common\Http\Client as OmnipayClient;
use Omnipay\Omnipay;
use Omnipay\Stripe\PaymentIntentsGateway;
use Omnitrade\Exception\InvalidNotificationException;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Stripe, through omnipay/stripe: the Checkout gateway (hosted payment page)
 * and the PaymentIntents gateway (what was paid, refunds), both on one
 * secret key. Omnipay talks through the application's HTTP client when one
 * is given (a PSR-18 bridge): the profiler sees the calls, the tests mock them.
 */
final class Api
{
    public const SIGNATURE_HEADER = 'Stripe-Signature';

    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $webhookSecret = null,
        private readonly ?HttpClientInterface $http = null,
    ) {
    }

    public function checkout(): CheckoutGateway
    {
        /** @var CheckoutGateway */
        return $this->create('\\'.CheckoutGateway::class);
    }

    public function intents(): PaymentIntentsGateway
    {
        /** @var PaymentIntentsGateway */
        return $this->create('Stripe\PaymentIntents');
    }

    public function canVerify(): bool
    {
        return null !== $this->webhookSecret && '' !== $this->webhookSecret;
    }

    /**
     * Whether a webhook payload was signed by Stripe with the endpoint's
     * secret (Stripe-Signature: t=timestamp,v1=hmac), within $tolerance seconds.
     *
     * @throws InvalidNotificationException
     */
    public function verify(string $payload, ?string $header, int $tolerance = 300): void
    {
        if (!$this->canVerify()) {
            throw new InvalidNotificationException('stripe', 'No webhook secret: the notification cannot be checked.');
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', (string) $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ('t' === $key) {
                $timestamp = (int) $value;
            } elseif ('v1' === $key) {
                $signatures[] = $value;
            }
        }
        if (null === $timestamp || abs(time() - $timestamp) > $tolerance) {
            throw new InvalidNotificationException('stripe', 'The notification is unsigned, or too old.');
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, (string) $this->webhookSecret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new InvalidNotificationException('stripe', 'The notification\'s signature does not match.');
    }

    private function create(string $gateway): \Omnipay\Common\GatewayInterface
    {
        $gateway = Omnipay::create($gateway, $this->http ? new OmnipayClient(new Psr18Client($this->http)) : null);
        $gateway->setApiKey($this->apiKey);

        return $gateway;
    }
}
