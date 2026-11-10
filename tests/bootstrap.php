<?php declare(strict_types=1);

/*
 * Minimal autoloader for the unit tests. The calculator has no Shopware
 * dependencies, so the tests run without booting Shopware.
 */
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Ms\\LeadTime\\Tests\\' => __DIR__ . '/',
        'Ms\\LeadTime\\' => __DIR__ . '/../src/',
    ];

    foreach ($prefixes as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
