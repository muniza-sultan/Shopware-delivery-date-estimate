<?php declare(strict_types=1);

namespace Ms\LeadTime\Storefront;

use Ms\LeadTime\Service\DeliveryEstimateProvider;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Attaches the delivery estimate to the product detail page, where the Twig
 * override in delivery-information.html.twig picks it up.
 */
final class ProductPageSubscriber implements EventSubscriberInterface
{
    public const EXTENSION = 'msDeliveryEstimate';

    public function __construct(
        private readonly DeliveryEstimateProvider $provider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $product = $page->getProduct();
        $deliveryTime = $product->getDeliveryTime();

        if ($deliveryTime === null) {
            return;
        }

        // Out of stock: core shows a restock time instead, so no date promise from us.
        if ($product->getAvailableStock() < ($product->getMinPurchase() ?? 1)) {
            return;
        }

        $estimate = $this->provider->estimate(
            $deliveryTime->getMin(),
            $deliveryTime->getMax(),
            $deliveryTime->getUnit(),
            $event->getSalesChannelContext()->getSalesChannelId(),
        );

        if ($estimate !== null) {
            $page->addExtension(self::EXTENSION, $estimate);
        }
    }
}
