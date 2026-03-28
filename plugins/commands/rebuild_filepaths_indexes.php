<?php
/**
 * Action: rebuild-filepaths-indexes
 * Migrated from: inline action trong index.php (Phase 2)
 */
function rebuild_filepaths_indexes($builder) {
    $repository = \CloudPad\Core\Request::require('repository');

    $filepaths = $builder->getRepoManager()->getProjectFilePaths($repository, true);

    $_SESSION['recentfilepaths'] = [];

    if (isset($_SESSION['filepaths'][$repository])) {
        foreach ($_SESSION['filepaths'][$repository] as $filename => $filepath) {
            if (!file_exists($filepath) || !in_array($filepath, $filepaths)) {
                unset($_SESSION['filepaths'][$repository][$filename]);
            }
        }
    }

    if (isset($_SESSION['openfilepaths'][$repository])) {
        foreach ($_SESSION['openfilepaths'][$repository] as $filename => $filepath) {
            if (!file_exists($filepath) || !in_array($filepath, $filepaths)) {
                unset($_SESSION['openfilepaths'][$repository][$filename]);
            }
        }
    }

    \CloudPad\Core\Response::ok(['count' => count($filepaths)]);
}
