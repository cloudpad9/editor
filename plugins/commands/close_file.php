<?php
/**
 * Action: close-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function close_file($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');
    $standalone = \CloudPad\Core\Request::getBool('standalone');

    $builder->getEditorService()->closeFile($filename, $repository, $standalone);
}
