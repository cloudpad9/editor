<?php
/**
 * Action: open-file-by-name
 * Migrated from: inline action trong index.php (Phase 2)
 */
function open_file_by_name($builder) {
    $repository = \CloudPad\Core\Request::require('repository');
    $filename   = \CloudPad\Core\Request::require('filename');
    $fromcache  = \CloudPad\Core\Request::getInt('fromcache', 0);

    $_SESSION['repository'] = $repository;

    $builder->getEditorService()->openFileByName($filename, $fromcache, $repository);
}
