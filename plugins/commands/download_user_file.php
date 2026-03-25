<?php
/**
 * Action: download-user-file
 * Migrated from: inline action trong index.php (Phase 2)
 */
function download_user_file($builder) {
    $name      = \CloudPad\Core\Request::getString('name');
    $directory = \CloudPad\Core\Request::getString('directory');

    if (empty($name)) {
        \CloudPad\Core\Response::fail('Missing file name.');
    }

    if (!empty($directory)) {
        $name = $directory . '/' . $name;
        $_SESSION['files.download.directory'] = $directory;
    }

    $builder->download_user_file($name);
}
