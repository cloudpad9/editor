<?php
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\ValidationException;

/**
 * git_diff — Diff file cụ thể hoặc toàn bộ repo.
 *
 * Params:
 *   repository  string   Repository code
 *   path        string   File path
 *   scope       string   "file" (default) | "all"
 *
 * Phase 5: gộp git_diff + git_diff_all → scope param
 * Phase 6.3: json_fail() → throw exceptions
 */
function git_diff($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $path       = \CloudPad\Core\Request::getString('path');
    $scope      = \CloudPad\Core\Request::getString('scope', 'file');

    $filepath = $builder->getAbsoluteFilePath($path, $repository);
    if (empty($filepath) || !file_exists($filepath)) {
        throw new NotFoundException('Source file not found.');
    }

    $info = $builder->get_git_info($filepath);
    if (empty($info) || empty($info['ok'])) {
        throw new ValidationException($info['message'] ?? 'Not a git repository or cannot determine repository root.');
    }

    $toplevel = $info['toplevel'];

    if ($scope === 'all') {
        $cmd = '--no-pager diff --no-color --';
    } else {
        $relpath = $info['relpath'];
        $cmd = '--no-pager diff --no-color -- ' . escapeshellarg($relpath);
    }

    $out = '';
    $ok  = $builder->execGitCommand($toplevel, $cmd, $out);

    // git diff exit code 1 = có diff — không phải lỗi
    if (!$ok && trim((string)$out) === '') {
        throw new ValidationException('git diff failed: ' . (string)$out);
    }

    json_ok(['output' => (string)$out]);
}
