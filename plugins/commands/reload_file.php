<?php
/**
 * Action: reload-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function reload_file($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');

    $builder->reload_file($filename, $repository);
}
