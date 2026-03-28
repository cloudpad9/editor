<?php
/**
 * Action: rebuild-sub-indexes
 * Migrated from: inline action trong index.php (Phase 2)
 */
function rebuild_sub_indexes($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');

    $builder->getEditorService()->rebuildSubIndexes($filename, $repository, true);
}
