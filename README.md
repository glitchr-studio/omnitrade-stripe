# omnitrade/stripe

Stripe for [glitchr/omnitrade](https://github.com/glitchr-studio/omnitrade): cards and wallets
through Checkout (Stripe's hosted page), what was paid, refunds, the webhook's events, and the
catalogue kept in Stripe's Products - on omnipay/stripe, through the application's HTTP client.

```yaml
omnitrade:
    gateways:
        card:
            factory: stripe
            options:
                api_key: '%env(STRIPE_API_KEY)%'               # sk_live_... / sk_test_...
                webhook_secret: '%env(STRIPE_WEBHOOK_SECRET)%' # whsec_..., for notify()
                adaptive_pricing: false                        # true: the buyer's own currency may be offered
                payment_methods: []                            # ['card', 'sepa_debit']: the methods to insist on
```

`purchase()` opens a Checkout session and answers PENDING with the page's URL (`returnUrl`
required; Stripe appends `?session={CHECKOUT_SESSION_ID}` to it); `fetch()` reads the session
(paid, open, expired); `refund()` pays back on the session's charge, with an idempotency key;
`notify()` checks `Stripe-Signature` and reads the `checkout.session.*` events. No authorizations.

## Catalogue

`fetchProducts()` lists Stripe's Products (`starting_after`; a query through the products
search, `name~"…"`), `fetchProduct()` reads one by its `prod_…` id; each active Price is a
variant (the default price first) with its offer. Metadata are the attributes, `metadata.brand`
the brand; `images` the media. Stripe counts no stock: `fetchInventory()` is not supported.
`notify()` reads `product.*` and `price.*` into a `Notification` carrying the product. See
[docs/catalogue.md](docs/catalogue.md).

Credentials: a secret key from the Stripe Dashboard (Developers → API keys), and a webhook
endpoint for `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
`checkout.session.expired`, `checkout.session.async_payment_failed` (its signing secret is
`webhook_secret`). Locally, `stripe listen --forward-to <your webhook URL>` prints one.

License: LGPL-3.0-or-later.

## Connect and subscriptions

Express accounts for those the platform pays (`createAccount()`, `accountLink()`,
`fetchAccount()`), payments sent to them as destination charges
(`Payment::$destination`, `::$applicationFee`), subscriptions through Checkout
(`subscribe()`, `fetchSubscription()`, `cancelSubscription()`, `subscriptionPortal()`), and the
`account.updated` and `customer.subscription.*` events read by `notify()`. See
[docs/connect.md](docs/connect.md).
