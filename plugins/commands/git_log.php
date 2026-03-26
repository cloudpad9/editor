<?php
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\ValidationException;

/**
 * git_log — Log file cụ thể hoặc toàn bộ repo.
 *
 * Params:
 *   repository  string   Repository code
 *   path        string   File/directory path
 *   scope       string   "file" (default) | "all"
 *
 * Phase 5: gộp git_log + git_log_all → scope param
 * Phase 6.3: json_fail() → throw exceptions
 */
function git_log($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $path       = \CloudPad\Core\Request::getString('path');
    $scope      = \CloudPad\Core\Request::getString('scope', 'file');

    $filepath = $builder->getAbsoluteFilePath($path, $repository);
    if (empty($filepath)) {
        throw new NotFoundException('Source file not found.');
    }

    $info = $builder->get_git_info($filepath);
    if (empty($info) || empty($info['ok'])) {
        throw new ValidationException($info['message'] ?? 'Not a valid git repository root.');
    }

    $toplevel = $info['toplevel'];

    if ($scope === 'all') {
        $cmd = '--no-pager log --stat -n 20';
    } else {
        if (is_dir($filepath)) {
            $cmd = '--no-pager log --stat -n 20 -- ' . escapeshellarg($filepath);
        } else {
            $cmd = '--no-pager log -p -- ' . escapeshellarg($filepath);
        }
    }

    $out = '';
    $builder->execGitCommand($toplevel, $cmd, $out);

    json_ok(['output' => (string)$out]);
}
