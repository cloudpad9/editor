<?php
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\PermissionDeniedException;

/**
 * fs_chmod — Thay đổi quyền file.
 * Phase 6.3: $builder->json_response() → throw exceptions
 */
function fs_chmod($builder) {
    $file       = \CloudPad\Core\Request::getString('file');
    $repository = \CloudPad\Core\Request::getString('repository');
    $mode       = \CloudPad\Core\Request::getString('mode') ?: '755';

    if (empty($file)) {
        throw new ValidationException('Missing file parameter.');
    }

    if (!preg_match('/^[0-7]{3,4}$/', $mode)) {
        throw new ValidationException('Invalid mode. Use octal format (e.g. 755).');
    }

    if (!empty($repository) && !$builder->hasRepositoryPermission($repository)) {
        throw new PermissionDeniedException('Permission denied.');
    }

    $command = 'chmod ' . escapeshellarg($mode) . ' ' . escapeshellarg($file);
    $builder->execute_linux($command);
}
