<?php

namespace Omnitrade\Stripe\Action;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Action\ApiAwareInterface;
use Omnitrade\Action\ApiAwareTrait;
use Omnitrade\Request\FetchProduct;
use Omnitrade\Request\Request;
use Omnitrade\Stripe\Api;
use Omnitrade\Stripe\Products;

/**
 * One Stripe product by its id (prod_...), with its active prices. Stripe
 * hosts no product pages: an address answers null, as an unknown id does.
 */
final class FetchProductAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof FetchProduct;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof FetchProduct);
        $request->setResult($request->reference->isUrl() ? null : Products::find($this->api, (string) $request->reference->id));
    }
}
