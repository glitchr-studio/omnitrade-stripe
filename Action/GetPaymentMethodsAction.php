<?php

namespace Omnitrade\Stripe\Action;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Model\PaymentMethod;
use Omnitrade\Request\GetPaymentMethods;
use Omnitrade\Request\Request;

/**
 * What Checkout offers: the methods insisted on in the options, else the
 * card plus the wallets Stripe shows on its own (Apple Pay, Google Pay, Link)
 * - Stripe decides the rest per account, country and currency on its page.
 */
final class GetPaymentMethodsAction implements ActionInterface
{
    private const KNOWN = [
        'card' => ['Card', PaymentMethod::CARD],
        'apple_pay' => ['Apple Pay', PaymentMethod::WALLET],
        'google_pay' => ['Google Pay', PaymentMethod::WALLET],
        'link' => ['Link', PaymentMethod::WALLET],
        'paypal' => ['PayPal', PaymentMethod::WALLET],
        'sepa_debit' => ['SEPA Direct Debit', PaymentMethod::BANK],
        'bancontact' => ['Bancontact', PaymentMethod::BANK],
        'ideal' => ['iDEAL', PaymentMethod::BANK],
        'eps' => ['EPS', PaymentMethod::BANK],
        'klarna' => ['Klarna', PaymentMethod::BUY_NOW_PAY_LATER],
        'afterpay_clearpay' => ['Afterpay / Clearpay', PaymentMethod::BUY_NOW_PAY_LATER],
    ];

    /** @param string[] $paymentMethods */
    public function __construct(private readonly array $paymentMethods = [])
    {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof GetPaymentMethods;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof GetPaymentMethods);
        $codes = $this->paymentMethods ?: ['card', 'apple_pay', 'google_pay', 'link'];
        $methods = [];
        foreach ($codes as $code) {
            [$label, $kind] = self::KNOWN[$code] ?? [ucfirst(str_replace('_', ' ', $code)), PaymentMethod::OTHER];
            $methods[] = new PaymentMethod($code, $label, $kind);
        }
        $request->setResult($methods);
    }
}
