<?php
/**
 * Minimal autoloader — được generate thủ công.
 * Khi có composer trên server, chạy: composer dump-autoload
 * để thay thế file này bằng autoloader đầy đủ của composer.
 *
 * Phase 8: Thêm autoload "files" cho helper functions.
 */

// ── PSR-4: CloudPad\ → src/ ───────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $prefix  = 'CloudPad\\';
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

// ── Autoload files (helper functions) ────────────────────────────────────
$_cloudpad_helpers = [
    __DIR__ . '/../src/Core/helpers.php',
    __DIR__ . '/../src/I18n/helpers.php',
];

foreach ($_cloudpad_helpers as $_cloudpad_file) {
    if (file_exists($_cloudpad_file)) {
        require_once $_cloudpad_file;
    }
}

unset($_cloudpad_helpers, $_cloudpad_file);
