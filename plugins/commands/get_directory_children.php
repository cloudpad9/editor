<?php
/**
 * Action: get-directory-children
 * Migrated from: inline action trong index.php (Phase 2)
 */
function get_directory_children($builder) {
    $repository = \CloudPad\Core\Request::require('repository');
    $path       = \CloudPad\Core\Request::getString('path');

    $builder->get_directory_children($repository, $path);
}
