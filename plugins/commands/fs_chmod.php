<?php
function fs_chmod($builder) {
    $file       = \CloudPad\Core\Request::getString('file') ?? '';
    $repository = \CloudPad\Core\Request::getString('repository') ?? '';
    // FIX: validate mode — chỉ cho phép octal 3-4 ký tự (vd: 644, 755, 0755)
    $mode       = \CloudPad\Core\Request::getString('mode') ?? '755';

    if (empty($file)) {
        $builder->json_response(['success' => false, 'message' => 'Missing file parameter.']);
    }

    if (!preg_match('/^[0-7]{3,4}$/', $mode)) {
        $builder->json_response(['success' => false, 'message' => 'Invalid mode. Use octal format (e.g. 755).']);
    }

    if (!empty($repository) && !$builder->hasRepositoryPermission($repository)) {
        $builder->json_response(['success' => false, 'message' => 'Permission denied.']);
    }

    // FIX: escapeshellarg() cho cả file lẫn mode để tránh Command Injection
    $command = 'chmod ' . escapeshellarg($mode) . ' ' . escapeshellarg($file);

    $builder->execute_linux($command);
}
