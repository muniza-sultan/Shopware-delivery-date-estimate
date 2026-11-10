<?php declare(strict_types=1);

namespace Ms\LeadTime\Service;

/**
 * Result of a delivery date calculation.
 */
final class DeliveryEstimate
{
    /**
     * @param \DateTimeImmutable      $earliest Earliest delivery day (time set to 00:00).
     * @param \DateTimeImmutable      $latest   Latest delivery day (time set to 00:00).
     * @param \DateTimeImmutable|null $orderBy  Cutoff the customer must order before for this
     *                                          estimate to hold. Null when the order already
     *                                          counts from a later day (after cutoff, weekend, holiday).
     */
    public function __construct(
        public readonly \DateTimeImmutable $earliest,
        public readonly \DateTimeImmutable $latest,
        public readonly ?\DateTimeImmutable $orderBy,
    ) {
    }
}
