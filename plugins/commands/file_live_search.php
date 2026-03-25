<?php
/**
 * Action: file-live-search
 * Migrated from: inline action trong index.php (Phase 2)
 */
function file_live_search($builder) {
    $repository = \CloudPad\Core\Request::require('repository');
    $filename   = \CloudPad\Core\Request::getString('filename');

    $builder->file_live_search($repository, $filename);
}
