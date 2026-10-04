<?php

namespace Omnitrade\Stripe;

use Omnitrade\Model\Account;
use Omnitrade\Model\Money;
use Omnitrade\Model\Subscription;

/** Stripe's account and subscription objects as Omnitrade's models. */
final class Accounts
{
    /** @param array<string, mixed> $account */
    public static function account(array $account): Account
    {
        $requirements = array_merge((array) ($account['requirements']['currently_due'] ?? []), (array) ($account['requirements']['past_due'] ?? []));

        return new Account(
            provider: 'stripe',
            reference: (string) $account['id'],
            type: (string) ($account['type'] ?? Account::EXPRESS),
            country: isset($account['country']) ? (string) $account['country'] : null,
            email: isset($account['email']) ? (string) $account['email'] : null,
            detailsSubmitted: (bool) ($account['details_submitted'] ?? false),
            chargesEnabled: (bool) ($account['charges_enabled'] ?? false),
            payoutsEnabled: (bool) ($account['payouts_enabled'] ?? false),
            requirements: array_values(array_unique(array_map('strval', $requirements))),
            defaultCurrency: isset($account['default_currency']) ? strtoupper((string) $account['default_currency']) : null,
            metadata: \is_array($account['metadata'] ?? null) ? $account['metadata'] : [],
            raw: $account,
        );
    }

    /** @param array<string, mixed> $subscription */
    public static function subscription(array $subscription): Subscription
    {
        $item = $subscription['items']['data'][0] ?? [];
        $price = \is_array($item['price'] ?? null) ? $item['price'] : [];
        $currency = strtoupper((string) ($price['currency'] ?? $subscription['currency'] ?? 'EUR'));
        $time = static fn ($t) => \is_int($t) ? (new \DateTimeImmutable())->setTimestamp($t) : null;
        $status = match ((string) ($subscription['status'] ?? '')) {
            'active' => Subscription::ACTIVE,
            'trialing' => Subscription::TRIALING,
            'past_due' => Subscription::PAST_DUE,
            'unpaid' => Subscription::UNPAID,
            'canceled', 'incomplete_expired' => Subscription::CANCELLED,
            'paused' => Subscription::PAUSED,
            default => Subscription::INCOMPLETE,
        };

        return new Subscription(
            provider: 'stripe',
            reference: (string) $subscription['id'],
            status: $status,
            customer: \is_array($subscription['customer'] ?? null) ? (string) ($subscription['customer']['id'] ?? '') : (isset($subscription['customer']) ? (string) $subscription['customer'] : null),
            amount: isset($price['unit_amount']) ? Money::of((int) $price['unit_amount'] * (int) ($item['quantity'] ?? 1), $currency) : null,
            interval: isset($price['recurring']['interval']) ? (string) $price['recurring']['interval'] : null,
            intervalCount: (int) ($price['recurring']['interval_count'] ?? 1),
            // The period moved from the subscription to its items in Stripe's newer API versions: read both.
            currentPeriodStart: $time($subscription['current_period_start'] ?? $item['current_period_start'] ?? null),
            currentPeriodEnd: $time($subscription['current_period_end'] ?? $item['current_period_end'] ?? null),
            cancelAtPeriodEnd: (bool) ($subscription['cancel_at_period_end'] ?? false),
            cancelledAt: $time($subscription['canceled_at'] ?? null),
            price: isset($price['id']) ? (string) $price['id'] : null,
            metadata: \is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : [],
            raw: $subscription,
        );
    }
}
