<?php
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * new_file — Tạo file mới trong repository.
 * Phase 6.3: Response::json(fail) → throw exceptions
 */
function new_file($builder) {
    $path       = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');
    $name       = \CloudPad\Core\Request::getString('name');

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

    $branchDir = rtrim($branchDir, '/');
    $dir       = $branchDir . '/' . $path;

    if (!is_dir($dir)) {
        throw new ValidationException('Operation failed.');
    }

    $newFilePath = rtrim($dir, '/') . '/' . $name;

    if (file_exists($newFilePath)) {
        throw new ValidationException("Destination file '$name' already exists.");
    }

    ob_start();
    $builder->getFileOps()->tryChmod('775', $dir);
    $output = ob_get_clean();

    if (!empty($output)) {
        throw new FileSystemException(str_replace($branchDir, '', $output));
    }

    file_put_contents($newFilePath, '');

    if (!file_exists($newFilePath)) {
        throw new FileSystemException("Operation failed. Cannot create file '$name'.");
    }

    json_ok();
}
