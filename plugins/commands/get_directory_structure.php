<?php
/**
 * Action: get-directory-structure
 * Migrated from: inline action trong index.php (Phase 2)
 */
function get_directory_structure($builder) {
    $repository = \CloudPad\Core\Request::require('repository');

    $builder->getEditorService()->getDirectoryStructure($repository);
}
