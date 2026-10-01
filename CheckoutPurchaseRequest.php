<?php

namespace Omnitrade\Stripe;

use Omnipay\Stripe\Message\Checkout\PurchaseRequest;

/**
 * omnipay/stripe's Checkout session request, with what it does not send:
 *
 *   customer_email       the buyer's address, filled in on Stripe's page
 *   adaptive_pricing     off by default: the session is paid in the given
 *                        currency, not converted to the buyer's
 *   locale               the page's language
 *   client_reference_id  the merchant's reference (the order's) - the parent's parameter, sent here
 *   metadata             kept by Stripe, given back on every event
 *   notice               a mention on the page and the receipt (why no VAT...)
 *
 * The methods to insist on (paymentMethodTypes) and the Idempotency-Key header
 * (setIdempotencyKeyHeader) are the parent's.
 */
class CheckoutPurchaseRequest extends PurchaseRequest
{
    public function getCustomerEmail(): ?string { return $this->getParameter('customerEmail'); }
    public function setCustomerEmail(?string $value): self { return $this->setParameter('customerEmail', $value); }
    public function getAdaptivePricing(): bool { return (bool) $this->getParameter('adaptivePricing'); }
    public function setAdaptivePricing(bool $value): self { return $this->setParameter('adaptivePricing', $value); }
    public function getLocale(): ?string { return $this->getParameter('locale'); }
    public function setLocale(?string $value): self { return $this->setParameter('locale', $value); }
    public function getNotice(): ?string { return $this->getParameter('notice'); }
    public function setNotice(?string $value): self { return $this->setParameter('notice', $value); }

    public function getData(): array
    {
        $data = array_filter(parent::getData(), static fn ($value) => null !== $value);
        if ($this->getCustomerEmail()) {
            $data['customer_email'] = $this->getCustomerEmail();
        }
        // Form-encoded: Stripe reads the strings "true" / "false".
        $data['adaptive_pricing'] = ['enabled' => $this->getAdaptivePricing() ? 'true' : 'false'];
        if ($this->getLocale()) {
            $data['locale'] = $this->getLocale();
        }
        if ($this->getClientReferenceId()) {
            $data['client_reference_id'] = $this->getClientReferenceId();
        }
        if ($this->getMetadata()) {
            $data['metadata'] = $this->getMetadata();
        }
        if ($this->getNotice()) {
            $data['custom_text'] = ['submit' => ['message' => $this->getNotice()]];
            $data['payment_intent_data'] = ['description' => $this->getNotice()];
        }

        return $data;
    }
}
