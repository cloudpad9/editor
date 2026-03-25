<?php
/**
 * Action: revert-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function revert_file($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');

    $builder->revert_file($filename, $repository);
}
