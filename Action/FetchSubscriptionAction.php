<?php

namespace Omnitrade\Stripe\Action;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Action\ApiAwareInterface;
use Omnitrade\Action\ApiAwareTrait;
use Omnitrade\Request\FetchSubscription;
use Omnitrade\Request\Request;
use Omnitrade\Stripe\Accounts;
use Omnitrade\Stripe\Api;

/** A subscription as it stands (GET /v1/subscriptions/{id}). */
final class FetchSubscriptionAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof FetchSubscription;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof FetchSubscription);
        $request->setResult(Accounts::subscription($this->api->get('/v1/subscriptions/'.rawurlencode($request->reference))));
    }
}
