<?php declare(strict_types=1);

namespace Ms\LeadTime\Checkout;

use Ms\LeadTime\Service\DeliveryEstimateProvider;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Replaces core's calendar-day delivery dates in the cart with the plugin's
 * business-day estimate, so checkout makes the same promise as the product page.
 *
 * Runs after core's DeliveryProcessor (lower priority, see services.xml): by then
 * the deliveries and their dates exist, and this class only corrects the dates.
 * Because the cart is recalculated from scratch every time, the correction never
 * stacks up, and because the order is created from the cart, the corrected dates
 * also end up in the order, the confirmation email and the admin.
 */
final class DeliveryDateProcessor implements CartProcessorInterface
{
    public function __construct(
        private readonly DeliveryEstimateProvider $provider,
    ) {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior,
    ): void {
        foreach ($toCalculate->getDeliveries() as $delivery) {
            $this->correctDelivery($delivery, $context->getSalesChannelId());
        }
    }

    private function correctDelivery(Delivery $delivery, string $salesChannelId): void
    {
        $earliest = null;
        $latest = null;
        $changed = false;

        foreach ($delivery->getPositions() as $position) {
            $date = $this->estimateFor($position, $salesChannelId);

            if ($date !== null) {
                // Each cart line shows its own position's date ("Delivery period: ...").
                $position->assign(['deliveryDate' => $date]);
                $changed = true;
            } else {
                // No estimate for this line (no delivery time, out of stock, ...):
                // keep core's date, but still take it into account below.
                $date = $position->getDeliveryDate();
            }

            // A delivery arrives when its slowest position is ready, like in core.
            if ($earliest === null || $date->getEarliest() > $earliest) {
                $earliest = $date->getEarliest();
            }
            if ($latest === null || $date->getLatest() > $latest) {
                $latest = $date->getLatest();
            }
        }

        if ($changed && $earliest !== null && $latest !== null) {
            // assign() works on the protected property, so no setter is needed.
            $delivery->assign(['deliveryDate' => new DeliveryDate($earliest, $latest)]);
        }
    }

    private function estimateFor(DeliveryPosition $position, string $salesChannelId): ?DeliveryDate
    {
        $information = $position->getLineItem()->getDeliveryInformation();
        $deliveryTime = $information?->getDeliveryTime();

        if ($information === null || $deliveryTime === null) {
            return null;
        }

        // Not enough stock: core adds the restock time, which we leave to core.
        if ($information->getStock() < $position->getQuantity()) {
            return null;
        }

        $estimate = $this->provider->estimate(
            $deliveryTime->getMin(),
            $deliveryTime->getMax(),
            $deliveryTime->getUnit(),
            $salesChannelId,
        );

        return $estimate === null ? null : new DeliveryDate($estimate->earliest, $estimate->latest);
    }
}
