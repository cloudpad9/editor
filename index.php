<?php
/**
 * CloudPad9 — Entry Point
 *
 * Phase 8: index.php slim (~30 dòng).
 * Toàn bộ bootstrap logic đã chuyển sang:
 *   - src/App.php       — Application bootstrap
 *   - src/Builder.php   — Builder class + legacy helper classes
 *   - src/Core/helpers.php   — json_ok(), json_fail(), ...
 *   - src/I18n/helpers.php   — _t()
 */

require_once __DIR__ . '/vendor/autoload.php';

// Builder.php không nằm trong PSR-4 namespace (global class) — require thủ công
require_once __DIR__ . '/src/Builder.php';

session_start();

\CloudPad\App::boot(__DIR__)->run();
