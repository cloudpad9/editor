<?php
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * delete_file — Xoá file/thư mục trong repository.
 * Phase 6.3: $builder->json_response() → throw exceptions
 */
function delete_file($builder) {
    $path       = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');

    $filepath = $builder->getAbsolutePath($path, $repository);

    if (empty($filepath)) {
        throw new NotFoundException('Source file not found.');
    }

    ob_start();
    $builder->try_exec('unlink ' . escapeshellarg($filepath));
    $output = ob_get_clean();

    if (!empty($output)) {
        throw new FileSystemException($output);
    }

    if (file_exists($filepath)) {
        throw new FileSystemException('Operation failed.');
    }

    json_ok();
}
