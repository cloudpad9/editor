<?php
/**
 * Action: save-current-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function save_current_file($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');
    $content    = \CloudPad\Core\Request::getString('content');
    $autorev    = \CloudPad\Core\Request::getInt('autorev', 0);

    $builder->getEditorService()->saveCurrentFile($filename, $repository, $content, $autorev);
}
