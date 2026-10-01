<?php

namespace Omnitrade\Stripe\Tests;

use Omnitrade\Exception\InvalidNotificationException;
use Omnitrade\Model\Money;
use Omnitrade\Model\Status;
use Omnitrade\Request\Authorize;
use Omnitrade\Request\Purchase;
use Omnitrade\Stripe\StripeGatewayFactory;
use Omnitrade\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class StripeGatewayTest extends TestCase
{
    private const SECRET = 'whsec_test';

    /** @var list<array{string, string, string}> method, url, body */
    private array $calls = [];

    private function gateway(): \Omnitrade\GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = $options['body'] ?? '';
            if (\is_callable($body)) {
                $chunks = '';
                while ('' !== $chunk = $body(8192)) {
                    $chunks .= $chunk;
                }
                $body = $chunks;
            }
            $this->calls[] = [$method, $url, (string) $body];
            $path = (string) parse_url($url, \PHP_URL_PATH);

            return match (true) {
                'POST' === $method && '/v1/checkout/sessions' === $path => new MockResponse(json_encode(self::session('open', 'unpaid') + ['url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])),
                'GET' === $method && '/v1/checkout/sessions/cs_test_1' === $path => new MockResponse(json_encode(self::session('complete', 'paid') + ['payment_intent' => 'pi_test_1'])),
                'GET' === $method && '/v1/checkout/sessions/cs_test_gone' === $path => new MockResponse(json_encode(self::session('expired', 'unpaid'))),
                'GET' === $method && '/v1/payment_intents/pi_test_1' === $path => new MockResponse(json_encode(['id' => 'pi_test_1', 'object' => 'payment_intent', 'currency' => 'eur', 'latest_charge' => 'ch_test_1'])),
                'POST' === $method && '/v1/charges/ch_test_1/refund' === $path => new MockResponse(json_encode(['id' => 're_test_1', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 500, 'currency' => 'eur'])),
                default => new MockResponse(json_encode(['error' => ['message' => 'No such resource: '.$method.' '.$path]]), ['http_code' => 404]),
            };
        });

        return (new StripeGatewayFactory($http))->create(['api_key' => 'sk_test_x', 'webhook_secret' => self::SECRET]);
    }

    public function testAPurchaseOpensACheckoutSessionTheBuyerIsSentTo(): void
    {
        $gateway = $this->gateway();
        $transaction = $gateway->purchase(Fixtures::payment());

        self::assertTrue($transaction->isRedirect());
        self::assertSame('cs_test_1', $transaction->reference);
        self::assertSame('https://checkout.stripe.com/c/pay/cs_test_1', $transaction->redirectUrl);
        self::assertTrue($transaction->amount->equals(Money::of(1250, 'EUR')));

        [, , $body] = $this->calls[0];
        parse_str($body, $sent);
        self::assertSame('payment', $sent['mode']);
        self::assertSame('ORDER-1042', $sent['client_reference_id']);
        self::assertSame('camille@example.org', $sent['customer_email']);
        self::assertSame('1250', $sent['line_items'][0]['price_data']['unit_amount'], 'the line listed on the page');
        self::assertSame('Dix heures', $sent['line_items'][0]['price_data']['product_data']['name']);
        self::assertSame('Autoliquidation', $sent['custom_text']['submit']['message'], 'the notice on the page');
        self::assertSame('false', $sent['adaptive_pricing']['enabled']);
        self::assertStringContainsString('session={CHECKOUT_SESSION_ID}', $sent['success_url'], 'the braces as Stripe wants them, not URL-encoded');
        self::assertSame('https://shop.example/panier', $sent['cancel_url']);
        self::assertTrue($gateway->supports(Purchase::class));
        self::assertFalse($gateway->supports(Authorize::class), 'Checkout takes the money when the buyer pays');
    }

    public function testASessionIsFetchedAsWhereItStands(): void
    {
        $gateway = $this->gateway();
        self::assertTrue($gateway->fetch('cs_test_1')->isPaid());
        self::assertSame(Status::EXPIRED, $gateway->fetch('cs_test_gone')->status);
    }

    public function testARefundGoesOnTheSessionsChargeWithItsIdempotencyKey(): void
    {
        $refund = $this->gateway()->refund('cs_test_1', Money::of(500, 'EUR'), 'refund-42');

        self::assertSame('re_test_1', $refund->reference);
        self::assertSame(Status::REFUNDED, $refund->status);
        self::assertSame(500, $refund->amount->amount);
        $post = array_values(array_filter($this->calls, fn ($c) => 'POST' === $c[0] && str_ends_with((string) parse_url($c[1], \PHP_URL_PATH), '/refund')))[0];
        self::assertStringEndsWith('/v1/charges/ch_test_1/refund', (string) parse_url($post[1], \PHP_URL_PATH), 'the session -> its payment intent -> its charge');
        parse_str($post[2], $sent);
        self::assertSame('500', $sent['amount']);
    }

    public function testANotificationIsCheckedThenRead(): void
    {
        $gateway = $this->gateway();
        $event = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => self::session('complete', 'paid')]]);
        $time = time();
        $header = sprintf('t=%d,v1=%s', $time, hash_hmac('sha256', $time.'.'.$event, self::SECRET));

        $notification = $gateway->notify($event, ['Stripe-Signature' => $header]);
        self::assertSame('checkout.session.completed', $notification->event);
        self::assertSame('cs_test_1', $notification->reference);
        self::assertSame(Status::PAID, $notification->status);
        self::assertTrue($notification->transaction->isPaid());
        self::assertSame('evt_1', $notification->id);

        $this->expectException(InvalidNotificationException::class);
        $gateway->notify($event, ['Stripe-Signature' => sprintf('t=%d,v1=%s', $time, str_repeat('0', 64))]);
    }

    public function testThePaymentMethodsAreWhatCheckoutOffers(): void
    {
        $methods = $this->gateway()->paymentMethods();
        self::assertSame('card', $methods[0]->code);
        self::assertContains('apple_pay', array_map(fn ($m) => $m->code, $methods));
    }

    private static function session(string $status, string $paymentStatus): array
    {
        return ['id' => 'cs_test_1', 'object' => 'checkout.session', 'status' => $status, 'payment_status' => $paymentStatus, 'amount_total' => 1250, 'currency' => 'eur', 'payment_method_types' => ['card'], 'created' => 1760000000, 'metadata' => []];
    }
}
