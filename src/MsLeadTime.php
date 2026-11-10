<?php declare(strict_types=1);

namespace Ms\LeadTime;

use Shopware\Core\Framework\Plugin;

/**
 * Plugin entry point. Shopware finds this class through
 * "extra.shopware-plugin-class" in composer.json.
 *
 * No lifecycle code needed: the plugin reads Shopware's own product
 * delivery times and stores its settings via config.xml, so there is
 * nothing to create on install or remove on uninstall.
 */
class MsLeadTime extends Plugin
{
}
