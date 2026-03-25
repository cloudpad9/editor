<?php
/**
 * Action: revert-sync-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function revert_sync_file($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');

    $builder->sync_file($filename, $repository, true);
}
