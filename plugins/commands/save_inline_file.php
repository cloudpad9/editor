<?php
/**
 * Action: save-inline-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function save_inline_file($builder) {
    $content = \CloudPad\Core\Request::getString('content');
    $file    = $_SESSION['inline-file'] ?? '';

    if (empty($file)) {
        \CloudPad\Core\Response::fail('No inline file is open.');
    }

    $builder->file_put_contents($file, $content);

    \CloudPad\Core\Response::ok();
}
