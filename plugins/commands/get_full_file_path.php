<?php
function get_full_file_path($builder) {
    $path = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');

    $path = $builder->getAbsolutePath($path, $repository);

    if (empty($path)) {
        $builder->json_response(array('success' => false, 'message' => 'Path not found'));
    }

    $builder->json_response(array('success' => true, 'path' => $path));
}
