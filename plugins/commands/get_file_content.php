<?php
/**
 * Action: get-file-content
 * Migrated from: inline action trong index.php (Phase 2)
 */
function get_file_content($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');

    $builder->getEditorService()->getFileContent($filename, $repository);
}
