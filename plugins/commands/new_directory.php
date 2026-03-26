<?php
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * new_directory — Tạo thư mục mới trong repository.
 * Phase 6.3: Response::json(fail) → throw exceptions
 */
function new_directory($builder) {
    $path          = \CloudPad\Core\Request::getString('path');
    $repository    = \CloudPad\Core\Request::getString('repository');
    $directoryName = \CloudPad\Core\Request::getString('name');

    $settings = $builder->getRepositorySettings($repository);
    $dirs     = $settings['dirs'] ?? [];

    $branch = '';
    if (preg_match('/^([0-9]+):\/\/(.*)/', trim($path), $match)) {
        $branch = $match[1];
        $path   = $match[2];
    }

    $branchDir = isset($dirs[$branch - 1]) ? $dirs[$branch - 1] : '';

    if (empty($branchDir) || !is_dir($branchDir)) {
        throw new ValidationException('Operation failed.');
    }

    $branchDir = rtrim($branchDir, '/');
    $dir       = $branchDir . '/' . $path;

    if (!is_dir($dir)) {
        throw new ValidationException('Operation failed.');
    }

    $newDirectoryPath = rtrim($dir, '/') . '/' . $directoryName;

    if (is_dir($newDirectoryPath)) {
        throw new ValidationException("Destination directory '$directoryName' already exists.");
    }

    ob_start();
    $builder->try_exec('mkdir -p ' . escapeshellarg($newDirectoryPath));
    $builder->try_exec('chmod 755 ' . escapeshellarg($newDirectoryPath));
    $output = ob_get_clean();

    if (!empty($output)) {
        throw new FileSystemException(str_replace($branchDir, '', $output));
    }

    if (!is_dir($newDirectoryPath)) {
        throw new FileSystemException("Operation failed. Cannot create directory '$directoryName'.");
    }

    json_ok();
}
