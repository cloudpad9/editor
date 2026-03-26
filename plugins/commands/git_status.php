<?php
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\ValidationException;

/**
 * git_status — Trả git status của repo chứa file hiện tại.
 * Phase 6.3: json_fail() → throw exceptions
 */
function git_status($builder) {
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
    if (!$builder->execGitCommand($toplevel, '--no-pager status --short --branch', $out)) {
        throw new ValidationException('git status failed: ' . (string)$out);
    }

    json_ok([
        'output'        => (string)$out,
        'toplevel'      => $toplevel,
        'git_root_path' => $builder->getRepositoryWisePath($toplevel, $repository, $path),
    ]);
}
