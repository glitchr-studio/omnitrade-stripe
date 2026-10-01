<?php

namespace Omnitrade\Stripe;

use Omnitrade\Model\Money;
use Omnitrade\Model\Status;
use Omnitrade\Model\Transaction;

/** A Checkout session, as Stripe answers it, read as a Transaction. */
final class Sessions
{
    /** @param array<string, mixed> $session */
    public static function transaction(array $session): Transaction
    {
        $status = match (true) {
            'paid' === ($session['payment_status'] ?? null), 'no_payment_required' === ($session['payment_status'] ?? null) => Status::PAID,
            'expired' === ($session['status'] ?? null) => Status::EXPIRED,
            default => Status::PENDING,
        };
        $intent = $session['payment_intent'] ?? null;
        $refunded = \is_array($intent) ? ($intent['amount_refunded'] ?? ($intent['latest_charge']['amount_refunded'] ?? null)) : null;
        if (Status::PAID === $status && \is_int($refunded) && $refunded > 0) {
            $status = $refunded >= (int) ($session['amount_total'] ?? 0) ? Status::REFUNDED : Status::PARTIALLY_REFUNDED;
        }
        $currency = strtoupper((string) ($session['currency'] ?? 'EUR'));

        return new Transaction(
            provider: 'stripe',
            reference: (string) $session['id'],
            status: $status,
            amount: isset($session['amount_total']) ? Money::of((int) $session['amount_total'], $currency) : null,
            redirectUrl: Status::PENDING === $status ? ($session['url'] ?? null) : null,
            message: $session['status'] ?? null,
            method: \is_array($session['payment_method_types'] ?? null) ? (string) ($session['payment_method_types'][0] ?? null) : null,
            refunded: \is_int($refunded) && $refunded > 0 ? Money::of($refunded, $currency) : null,
            metadata: \is_array($session['metadata'] ?? null) ? $session['metadata'] : [],
            createdAt: isset($session['created']) ? (new \DateTimeImmutable())->setTimestamp((int) $session['created']) : null,
            raw: $session,
        );
    }
}
