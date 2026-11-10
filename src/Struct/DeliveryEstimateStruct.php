<?php declare(strict_types=1);

namespace Ms\LeadTime\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Shopware-facing wrapper around a DeliveryEstimate, attached to the product
 * page as the extension "msDeliveryEstimate" and read by the Twig template.
 *
 * Extensions must be Struct instances, which is why the pure DeliveryEstimate
 * from the Service namespace is wrapped instead of extending Struct itself.
 */
final class DeliveryEstimateStruct extends Struct
{
    public function __construct(
        public readonly \DateTimeImmutable $earliest,
        public readonly \DateTimeImmutable $latest,
        public readonly ?\DateTimeImmutable $orderBy,
        // The shop time zone, so Twig formats the dates without shifting them a day.
        public readonly string $timezone,
    ) {
    }
}
