<?php
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\ValidationException;

/**
 * git_pull — Pull latest changes từ remote.
 *
 * Phase 6.3: json_fail() → throw exceptions
 */
function git_pull($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $path       = \CloudPad\Core\Request::getString('path');

    $filepath = $builder->getAbsoluteFilePath($path, $repository);
    if (empty($filepath)) {
        throw new NotFoundException('Source file not found.');
    }

    $info = $builder->get_git_info($filepath);
    if (empty($info) || empty($info['ok'])) {
        throw new ValidationException($info['message'] ?? 'Not a valid git repository root.');
    }

    $toplevel = $info['toplevel'];

    $out = '';
    if (!$builder->execGitCommand($toplevel, 'pull --no-rebase --no-edit', $out)) {
        throw new ValidationException('git pull failed: ' . (string)$out);
    }

    json_ok(['output' => (string)$out]);
}
