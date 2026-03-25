<?php
function new_directory($builder) {
    $path = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');
    $directoryName = \CloudPad\Core\Request::getString('name');

    $settings = $builder->getRepositorySettings($repository);
    $dirs = $settings['dirs'];

    // Extract branch, path
    $branch = '';

    if (preg_match('/^([0-9]+)\:\/\/(.*)/', trim($path), $match)) {
        $branch = $match[1];
        $path = $match[2];
    }

    // Lấy branch dir
    $branchDir = isset($dirs[$branch - 1])? $dirs[$branch - 1] : '';

    if (!empty($branchDir) && is_dir($branchDir)) {
        $branchDir = rtrim($branchDir, '/');

        $dir = $branchDir.'/'.$path;

        if (is_dir($dir)) {
            $newDirectoryPath = rtrim($dir, '/').'/'.$directoryName;

            if (is_dir($newDirectoryPath)) {
                \CloudPad\Core\Response::json(array('success' => false, 'message' => "Destination directory '$directoryName' already exists."));

                return;
            }

            ob_start();

            $builder->try_exec('mkdir -p ' . escapeshellarg($newDirectoryPath));
            $builder->try_exec('chmod 755 ' . escapeshellarg($newDirectoryPath));

            $output = ob_get_clean();

            if (!empty($output)) {
                $output = str_replace($branchDir, '', $output);

                \CloudPad\Core\Response::json(array('success' => false, 'message' => $output));

                return;
            }

            if (!is_dir($newDirectoryPath)) {
                \CloudPad\Core\Response::json(array('success' => false, 'message' => "Operation failed. Cannot create directory '$directoryName'"));

                return;
            }

            \CloudPad\Core\Response::json(array('success' => true));
            exit;
        }
    }

    \CloudPad\Core\Response::json(array('success' => false, 'message' => "Operation failed"));
}
