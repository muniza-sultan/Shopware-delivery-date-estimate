<?php declare(strict_types=1);

namespace Ms\LeadTime\Service;

use Ms\LeadTime\Struct\DeliveryEstimateStruct;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * The Shopware glue around the pure calculator: reads the plugin settings for
 * the current sales channel, supplies "now" and the holiday list, and turns
 * every problem into "no estimate" instead of a broken page.
 *
 * Used by the product page subscriber (step 7) and the cart processor (step 8),
 * so both places always make the same promise.
 */
final class DeliveryEstimateProvider
{
    private const CONFIG = 'MsLeadTime.config.';
    private const DEFAULT_TIMEZONE = 'Europe/Berlin';
    private const DEFAULT_CUTOFF_HOUR = 14;

    public function __construct(
        private readonly SystemConfigService $config,
        private readonly DeliveryEstimateCalculator $calculator,
        private readonly HolidayListParser $holidayParser,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function estimate(int $min, int $max, string $unit, ?string $salesChannelId): ?DeliveryEstimateStruct
    {
        if ($this->config->get(self::CONFIG . 'active', $salesChannelId) === false) {
            return null;
        }

        $timezone = $this->timezone($salesChannelId);
        $now = new \DateTimeImmutable('now', $timezone);

        $holidays = $this->holidayParser->parse(
            (string) $this->config->get(self::CONFIG . 'holidays', $salesChannelId),
            (int) $now->format('Y'),
            (int) $now->format('Y') + 1, // so late-December orders see January holidays
        );

        if ($holidays['invalid'] !== []) {
            $this->logger->warning('MsLeadTime: ignoring invalid holiday lines in the plugin settings.', [
                'lines' => $holidays['invalid'],
                'salesChannelId' => $salesChannelId,
            ]);
        }

        try {
            $estimate = $this->calculator->calculate(
                $min,
                $max,
                $unit,
                $now,
                $this->cutoffHour($salesChannelId),
                $this->config->get(self::CONFIG . 'businessDaysOnly', $salesChannelId) !== false,
                $holidays['dates'],
            );
        } catch (\InvalidArgumentException $e) {
            // e.g. a delivery time with an unknown unit: show core's text only.
            $this->logger->warning('MsLeadTime: could not calculate a delivery estimate.', [
                'error' => $e->getMessage(),
                'min' => $min,
                'max' => $max,
                'unit' => $unit,
            ]);

            return null;
        }

        return new DeliveryEstimateStruct(
            $estimate->earliest,
            $estimate->latest,
            $estimate->orderBy,
            $timezone->getName(),
        );
    }

    private function cutoffHour(?string $salesChannelId): int
    {
        $value = $this->config->get(self::CONFIG . 'cutoffHour', $salesChannelId);

        return is_numeric($value) ? max(0, min(23, (int) $value)) : self::DEFAULT_CUTOFF_HOUR;
    }

    private function timezone(?string $salesChannelId): \DateTimeZone
    {
        $name = (string) $this->config->get(self::CONFIG . 'timezone', $salesChannelId);

        try {
            return new \DateTimeZone($name !== '' ? $name : self::DEFAULT_TIMEZONE);
        } catch (\Exception) {
            $this->logger->warning('MsLeadTime: unknown time zone in the plugin settings, using the default.', [
                'timezone' => $name,
            ]);

            return new \DateTimeZone(self::DEFAULT_TIMEZONE);
        }
    }
}
