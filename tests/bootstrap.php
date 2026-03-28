<?php
/**
 * PHPUnit bootstrap — loads autoloader and defines constants.
 */

define('BUILDER_DIR', dirname(__DIR__));

// Composer autoloader (run: composer install --dev first)
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    // Minimal fallback: load helpers and key classes manually for CI without vendor/
    require_once __DIR__ . '/../src/Core/helpers.php';
    require_once __DIR__ . '/../src/I18n/helpers.php';

    spl_autoload_register(function (string $class): void {
        $prefix = 'CloudPad\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
        $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = __DIR__ . '/../src/' . $rel . '.php';
        if (file_exists($file)) require_once $file;
    });
}
