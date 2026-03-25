<?php
function delete_file($builder) {
    $path = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');

    $filepath = $builder->getAbsolutePath($path, $repository);

    if (empty($filepath)) {
        $builder->json_response(array('success' => false, 'message' => 'Source file not found.'));
    }

    ob_start();

    $builder->try_exec('unlink ' . escapeshellarg($filepath));

    $output = ob_get_clean();

    if (!empty($output)) {
        $builder->json_response(array('success' => false, 'message' => $output));
    }

    if (file_exists($filepath)) {
        $builder->json_response(array('success' => false, 'message' => "Operation failed"));
    }

    $builder->json_response(array('success' => true));
}
