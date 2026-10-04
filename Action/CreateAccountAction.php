<?php

namespace Omnitrade\Stripe\Action;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Action\ApiAwareInterface;
use Omnitrade\Action\ApiAwareTrait;
use Omnitrade\Request\CreateAccount;
use Omnitrade\Request\Request;
use Omnitrade\Stripe\Accounts;
use Omnitrade\Stripe\Api;

/**
 * A connected account (POST /v1/accounts): Express by default, in the
 * holder's country, with the capabilities asked for - "transfers" to be
 * sent destination charges, "card_payments" with it where Stripe requires
 * both.
 */
final class CreateAccountAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof CreateAccount;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof CreateAccount);
        $capabilities = [];
        foreach ($request->capabilities as $capability) {
            $capabilities[$capability] = ['requested' => true];
        }
        $request->setResult(Accounts::account($this->api->post('/v1/accounts', [
            'type' => $request->type,
            'country' => strtoupper($request->country),
            'email' => $request->email,
            'business_type' => $request->businessType,
            'capabilities' => $capabilities,
            'metadata' => $request->metadata ? array_map('strval', $request->metadata) : null,
        ], $request->idempotencyKey)));
    }
}
