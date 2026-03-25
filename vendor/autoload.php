<?php
/**
 * Minimal autoloader — được generate thủ công.
 * Khi có composer trên server, chạy: composer dump-autoload
 * để thay thế file này bằng autoloader đầy đủ của composer.
 */

// CloudPad PSR-4: CloudPad\ → src/
spl_autoload_register(function (string $class): void {
    $prefix = 'CloudPad\\';
    $baseDir = __DIR__ . '/../src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
