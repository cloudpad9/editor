<?php
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * new_hugo_content — Tạo file Hugo content mới qua `hugo new`.
 * Phase 6.3: Response::json(fail) → throw exceptions
 */
function new_hugo_content($builder) {
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

    $branchDir   = rtrim($branchDir, '/');
    $dir         = $branchDir . '/' . $path;
    $newFilePath = rtrim($dir, '/') . '/' . $name;

    if (!is_dir($dir)) {
        throw new ValidationException('Operation failed.');
    }

    if (file_exists($newFilePath)) {
        throw new ValidationException("Destination file '$name' already exists.");
    }

    ob_start();
    $builder->try_exec('chmod 755 ' . escapeshellarg($dir));
    $output = ob_get_clean();

    if (!empty($output)) {
        throw new FileSystemException(str_replace($branchDir, '', $output));
    }

    if (!_splitHugoPath($newFilePath, $contentDir, $relativePath, $projectDir)) {
        throw new ValidationException("Not a Hugo content directory: $path.");
    }

    ob_start();
    $builder->try_exec('cd ' . escapeshellarg($projectDir) . '; sudo hugo new ' . escapeshellarg($relativePath));
    $output = ob_get_clean();

    if (!file_exists($newFilePath)) {
        $hint = !empty($output) ? ' ' . str_replace($branchDir, '', $output) : '';
        throw new FileSystemException("Operation failed. Cannot create Hugo file '$relativePath'.$hint");
    }

    json_ok();
}

function _splitHugoPath($fullPath, &$contentDir, &$relativePath, &$projectDir) {
    if (strpos($fullPath, '/content/') === false) {
        return false;
    }

    $contentDir   = preg_replace('/\/content\/.*/', '/content', $fullPath);
    $projectDir   = str_replace('/content', '', $contentDir);
    $relativePath = str_replace($contentDir . '/', '', $fullPath);

    return true;
}
