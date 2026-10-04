<?php

namespace Omnitrade\Stripe\Action;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Action\ApiAwareInterface;
use Omnitrade\Action\ApiAwareTrait;
use Omnitrade\Request\CancelSubscription;
use Omnitrade\Request\Request;
use Omnitrade\Stripe\Accounts;
use Omnitrade\Stripe\Api;

/**
 * Stops a subscription: at the end of the paid period (POST, cancel_at_period_end)
 * or at once (DELETE /v1/subscriptions/{id}).
 */
final class CancelSubscriptionAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof CancelSubscription;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof CancelSubscription);
        $path = '/v1/subscriptions/'.rawurlencode($request->reference);
        $request->setResult(Accounts::subscription($request->atPeriodEnd ? $this->api->post($path, ['cancel_at_period_end' => true]) : $this->api->delete($path)));
    }
}
