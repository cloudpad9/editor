<?php
/**
 * Action: clone-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function clone_file($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');
    $newname    = \CloudPad\Core\Request::require('newname');

    $builder->getEditorService()->cloneFile($filename, $repository, $newname);
}
