<?php

namespace Omnitrade\Stripe\Action;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Action\ApiAwareInterface;
use Omnitrade\Action\ApiAwareTrait;
use Omnitrade\Request\FetchAccount;
use Omnitrade\Request\Request;
use Omnitrade\Stripe\Accounts;
use Omnitrade\Stripe\Api;

/** A connected account as it stands (GET /v1/accounts/{id}). */
final class FetchAccountAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof FetchAccount;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof FetchAccount);
        $request->setResult(Accounts::account($this->api->get('/v1/accounts/'.rawurlencode($request->reference))));
    }
}
