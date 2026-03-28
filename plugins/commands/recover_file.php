<?php
/**
 * Action: recover-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function recover_file($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');

    $builder->get(\CloudPad\Editor\RevisionManager::class)->recoverFile($filename, $repository);
}
