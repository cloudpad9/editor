<?php
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\PermissionDeniedException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * file_transfer — Copy hoặc Move files sang thư mục đích.
 *
 * Params:
 *   operation   string   "copy" (default) | "move"
 *   paths       array    Danh sách paths cần copy/move
 *   repository  string   Repository code
 *   to          string   Destination directory path
 *
 * Phase 5: gộp copy_files + move_files → operation param
 * Phase 6.3: $builder->json_response() → throw exceptions
 */
function file_transfer($builder) {
    $operation  = \CloudPad\Core\Request::getString('operation', 'copy');
    $paths      = \CloudPad\Core\Request::getArray('paths');
    $repository = \CloudPad\Core\Request::getString('repository');
    $toPath     = \CloudPad\Core\Request::getString('to');

    if (empty($repository) || empty($toPath)) {
        throw new ValidationException('Missing required parameters.');
    }

    if (!$builder->hasRepositoryPermission($repository)) {
        throw new PermissionDeniedException('Permission denied.');
    }

    if (!in_array($operation, ['copy', 'move'], true)) {
        throw new ValidationException('Invalid operation. Use "copy" or "move".');
    }

    $toDir = $builder->getAbsolutePath($toPath, $repository);
    if (!is_dir($toDir)) {
        throw new ValidationException("Destination '$toPath' is not a directory.");
    }

    $shellCmd = ($operation === 'move') ? 'mv' : 'cp';
    $toDir    = rtrim($toDir, '/') . '/';

    ob_start();
    foreach ($paths as $path) {
        $path = trim($path);
        if (empty($path)) continue;

        $absPath = $builder->getAbsolutePath($path, $repository);
        if (empty($absPath) || !file_exists($absPath)) continue;

        $builder->try_exec($shellCmd . ' ' . escapeshellarg($absPath) . ' ' . escapeshellarg($toDir));
    }
    $output = ob_get_clean();

    if (!empty($output)) {
        throw new FileSystemException($output);
    }

    json_ok();
}
