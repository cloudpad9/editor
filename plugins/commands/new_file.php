<?php
function new_file($builder) {
    $path = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');
    $name = \CloudPad\Core\Request::getString('name');

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
            $newFilePath = rtrim($dir, '/').'/'.$name;

            if (file_exists($newFilePath)) {
                \CloudPad\Core\Response::json(array('success' => false, 'message' => "Destination file '$name' already exists."));

                return;
            }

            ob_start();

            $builder->try_chmod(777, $dir);

            $output = ob_get_clean();

            if (!empty($output)) {
                $output = str_replace($branchDir, '', $output);

                \CloudPad\Core\Response::json(array('success' => false, 'message' => $output));

                return;
            }

            file_put_contents($newFilePath, '');

            if (!file_exists($newFilePath)) {
                \CloudPad\Core\Response::json(array('success' => false, 'message' => "Operation failed. Cannot create file '$name'"));

                return;
            }

            \CloudPad\Core\Response::json(array('success' => true));
            exit;
        }
    }

    \CloudPad\Core\Response::json(array('success' => false, 'message' => "Operation failed"));
}
