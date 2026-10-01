<?php

namespace Omnitrade\Stripe;

use Omnitrade\Config;
use Omnitrade\GatewayFactory;
use Omnitrade\Stripe\Action\FetchTransactionAction;
use Omnitrade\Stripe\Action\GetPaymentMethodsAction;
use Omnitrade\Stripe\Action\NotifyAction;
use Omnitrade\Stripe\Action\PurchaseAction;
use Omnitrade\Stripe\Action\RefundAction;

/**
 * Stripe: cards and wallets through Checkout (the hosted page), what was
 * paid, refunds, and the webhook's events.
 *
 *   options:
 *     api_key: '%env(STRIPE_API_KEY)%'              # sk_live_... / sk_test_...
 *     webhook_secret: '%env(STRIPE_WEBHOOK_SECRET)%' # whsec_..., for notify()
 *     adaptive_pricing: false                        # true: Stripe may offer the buyer's own currency
 *     payment_methods: []                            # ['card', 'sepa_debit']: the methods to insist on
 *
 * No authorizations: Checkout takes the money when the buyer pays (a session
 * in "setup"/"authorize" mode is not offered here).
 */
final class StripeGatewayFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnitrade.factory_name' => 'stripe',
            'omnitrade.factory_title' => 'Stripe',
            'omnitrade.required_options' => ['api_key'],
            'webhook_secret' => null,
            'adaptive_pricing' => false,
            'payment_methods' => [],
            'omnitrade.api' => fn (Config $c) => new Api((string) $c['api_key'], $c['webhook_secret'] ?: null, $this->http),
            'omnitrade.action.purchase' => static fn (Config $c) => new PurchaseAction((bool) $c['adaptive_pricing'], (array) $c['payment_methods']),
            'omnitrade.action.fetch' => new FetchTransactionAction(),
            'omnitrade.action.refund' => new RefundAction(),
            'omnitrade.action.notify' => new NotifyAction(),
            'omnitrade.action.methods' => static fn (Config $c) => new GetPaymentMethodsAction((array) $c['payment_methods']),
        ]);
    }
}
