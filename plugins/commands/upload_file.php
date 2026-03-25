<?php
function upload_file($builder) {
    $path = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');
    $name = \CloudPad\Core\Request::getString('name');

    $file = isset($_FILES['file'])? $_FILES['file'] : array();

    if (empty($file)) {
        \CloudPad\Core\Response::json(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    if ($file['error']) {
        \CloudPad\Core\Response::json(['success' => false, 'message' => 'Upload failed']);
        exit;
    }

    $fileName = $file['name'];
    $filePath = $file['tmp_name'];

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

            ob_start();

            $builder->try_exec('chmod 755 ' . escapeshellarg($dir));

            $output = ob_get_clean();

            if (!empty($output)) {
                $output = str_replace($branchDir, '', $output);

                \CloudPad\Core\Response::json(array('success' => false, 'message' => $output));

                return;
            }

            move_uploaded_file($filePath, $newFilePath);

            if (!file_exists($newFilePath)) {
                \CloudPad\Core\Response::json(array('success' => false, 'message' => "Operation failed. Cannot upload file '$name'"));

                return;
            }

            \CloudPad\Core\Response::json(array('success' => true));
            exit;
        }
    }

    \CloudPad\Core\Response::json(array('success' => false, 'message' => "Operation failed"));
}
