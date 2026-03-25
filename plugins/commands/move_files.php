<?php
function move_files($builder) {
    $paths      = \CloudPad\Core\Request::getArray('paths');
    $repository = \CloudPad\Core\Request::getString('repository');
    $toPath     = \CloudPad\Core\Request::getString('to');

    if (empty($repository) || empty($toPath)) {
        $builder->json_response(['success' => false, 'message' => 'Missing required parameters.']);
    }

    if (!$builder->hasRepositoryPermission($repository)) {
        $builder->json_response(['success' => false, 'message' => 'Permission denied.']);
    }

    $toDir = $builder->getAbsolutePath($toPath, $repository);

    if (!is_dir($toDir)) {
        $builder->json_response(['success' => false, 'message' => "Destination '$toPath' is not a directory."]);
    }

    ob_start();

    foreach ($paths as $path) {
        $path = trim($path);
        if (empty($path)) continue;

        $absPath = $builder->getAbsolutePath($path, $repository);

        if (empty($absPath) || !file_exists($absPath)) continue;

        // FIX: escapeshellarg() để tránh Command Injection / Path Traversal
        $builder->try_exec('mv ' . escapeshellarg($absPath) . ' ' . escapeshellarg($toDir));
    }

    $output = ob_get_clean();

    if (!empty($output)) {
        $builder->json_response(['success' => false, 'message' => $output]);
    }

    $builder->json_response(['success' => true]);
}
