<?php

namespace Omnitrade\Stripe\Action;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Action\ApiAwareInterface;
use Omnitrade\Action\ApiAwareTrait;
use Omnitrade\Exception\ProviderException;
use Omnitrade\Model\Notification;
use Omnitrade\Model\Status;
use Omnitrade\Request\Notify;
use Omnitrade\Request\Request;
use Omnitrade\Stripe\Api;
use Omnitrade\Stripe\Products;
use Omnitrade\Stripe\Sessions;

/**
 * A webhook event, its signature checked: the Checkout session events read
 * as what they mean for the transaction - completed and async_payment_succeeded
 * with a paid session is PAID, expired and async_payment_failed is EXPIRED /
 * REFUSED. Any other event is handed back with no status, to ignore or to
 * read from $raw.
 *
 * The catalogue's events carry the product: product.created and
 * product.updated the event's product with its active prices (one call),
 * price.created, price.updated and price.deleted the price's product, read
 * afresh (two calls); product.deleted the product's id alone. $reference is
 * the product's id.
 */
final class NotifyAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Notify;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Notify);
        $this->api->verify($request->body, $request->header(Api::SIGNATURE_HEADER));

        $event = json_decode($request->body, true);
        if (!\is_array($event) || !\is_string($event['type'] ?? null)) {
            throw new ProviderException('stripe', 'The notification is not a Stripe event.');
        }
        $object = \is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
        $session = 'checkout.session' === ($object['object'] ?? null) ? $object : null;
        $status = match ($event['type']) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => 'paid' === ($object['payment_status'] ?? null) ? Status::PAID : Status::PENDING,
            'checkout.session.expired' => Status::EXPIRED,
            'checkout.session.async_payment_failed' => Status::REFUSED,
            default => null,
        };

        $reference = isset($object['id']) ? (string) $object['id'] : null;
        $product = null;
        if ('product' === ($object['object'] ?? null) && null !== $reference && 'product.deleted' !== $event['type']) {
            $product = Products::fetch($this->api, $object);
        } elseif ('price' === ($object['object'] ?? null) && str_starts_with($event['type'], 'price.')) {
            $reference = \is_array($object['product'] ?? null) ? (string) ($object['product']['id'] ?? '') : (string) ($object['product'] ?? '');
            $product = '' === $reference ? null : Products::find($this->api, $reference);
        }

        $request->setResult(new Notification(
            provider: 'stripe',
            event: $event['type'],
            reference: $reference,
            status: $status,
            transaction: $session ? Sessions::transaction($session) : null,
            id: isset($event['id']) ? (string) $event['id'] : null,
            raw: $event,
            product: $product,
        ));
    }
}
