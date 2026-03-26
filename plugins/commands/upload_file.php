<?php
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * upload_file — Upload file vào thư mục trong repository.
 * Phase 6.3: Response::json(fail) → throw exceptions
 */
function upload_file($builder) {
    $path       = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');
    $name       = \CloudPad\Core\Request::getString('name');

    $file = $_FILES['file'] ?? [];

    if (empty($file)) {
        throw new ValidationException('Invalid request: no file uploaded.');
    }

    if (!empty($file['error'])) {
        throw new ValidationException('Upload failed (error code: ' . $file['error'] . ').');
    }

    $filePath = $file['tmp_name'];

    $settings  = $builder->getRepositorySettings($repository);
    $dirs      = $settings['dirs'] ?? [];

    $branch = '';
    if (preg_match('/^([0-9]+):\/\/(.*)/', trim($path), $match)) {
        $branch = $match[1];
        $path   = $match[2];
    }

    $branchDir = isset($dirs[$branch - 1]) ? $dirs[$branch - 1] : '';

    if (empty($branchDir) || !is_dir($branchDir)) {
        throw new ValidationException('Operation failed.');
    }

    $branchDir   = rtrim($branchDir, '/');
    $dir         = $branchDir . '/' . $path;
    $newFilePath = rtrim($dir, '/') . '/' . $name;

    if (!is_dir($dir)) {
        throw new ValidationException('Operation failed.');
    }

    ob_start();
    $builder->try_exec('chmod 755 ' . escapeshellarg($dir));
    $output = ob_get_clean();

    if (!empty($output)) {
        throw new FileSystemException(str_replace($branchDir, '', $output));
    }

    move_uploaded_file($filePath, $newFilePath);

    if (!file_exists($newFilePath)) {
        throw new FileSystemException("Operation failed. Cannot upload file '$name'.");
    }

    json_ok();
}
