<?php
/**
 * Action: open-inline-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function open_inline_file($builder) {
    $repository = \CloudPad\Core\Request::require('repository');
    $file       = \CloudPad\Core\Request::require('file');

    $filepath = $builder->searchForFile($file, $repository);

    if (empty($filepath)) {
        \CloudPad\Core\Response::fail("File not found: $file");
    }

    $_SESSION['inline-file'] = $filepath;

    echo $builder->file_get_contents($filepath);
}
