<?php
/**
 * Action: delete-user-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function delete_user_file($builder) {
    $name = \CloudPad\Core\Request::getString('name');

    if (empty($name)) {
        \CloudPad\Core\Response::fail('Missing file name.');
    }

    $builder->getEditorService()->deleteUserFile($name);
}
