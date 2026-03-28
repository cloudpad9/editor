<?php
/**
 * CloudPad9 — Entry Point
 *
 * Phase 8: index.php slim (~20 dòng).
 * R5: Builder moved into CloudPad namespace — PSR-4 autoloadable, no manual require.
 */

require_once __DIR__ . '/vendor/autoload.php';

session_start();

\CloudPad\App::boot(__DIR__)->run();
