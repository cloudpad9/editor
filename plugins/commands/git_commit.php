<?php
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\ValidationException;

/**
 * git_commit — Commit file(s) hoặc toàn bộ repo.
 *
 * Params:
 *   repository  string   Repository code
 *   path        string   File path (khi scope=file, comma-separated cho nhiều files)
 *   message     string   Commit message
 *   scope       string   "file" (default) | "all"
 *
 * Phase 5: gộp git_commit + git_commit_all → scope param
 * Phase 6.3: json_fail() → throw exceptions
 */
function git_commit($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $pathParam  = \CloudPad\Core\Request::getString('path');
    $message    = \CloudPad\Core\Request::getString('message');
    $scope      = \CloudPad\Core\Request::getString('scope', 'file');

    if (empty($message)) {
        $message = ($scope === 'all') ? 'Update all files' : 'Update file';
    }

    // ── scope=all: git add . ──────────────────────────────────────────────
    if ($scope === 'all') {
        $filepath = $builder->getAbsoluteFilePath($pathParam, $repository);
        if (empty($filepath)) {
            throw new NotFoundException('Source file not found.');
        }

        $info = $builder->get_git_info($filepath);
        if (empty($info) || empty($info['ok'])) {
            throw new ValidationException($info['message'] ?? 'Not a valid git repository root.');
        }

        $toplevel = $info['toplevel'];

        $outAdd = '';
        if (!$builder->execGitCommand($toplevel, 'add .', $outAdd)) {
            throw new ValidationException('git add failed: ' . (string)$outAdd);
        }

        $outCommit = '';
        $commitOk  = $builder->execGitCommand($toplevel, 'commit -m ' . escapeshellarg($message), $outCommit);

        if (!$commitOk) {
            $trimmed = trim((string)$outCommit);
            if (stripos($trimmed, 'nothing to commit') !== false) {
                json_ok(['message' => 'Nothing to commit (working tree clean).', 'output' => $outAdd . "\n" . $outCommit]);
                return;
            }
            throw new ValidationException('git commit failed: ' . $trimmed);
        }

        $outPush = '';
        if (!$builder->execGitCommand($toplevel, 'push', $outPush)) {
            throw new ValidationException('git push failed: ' . (string)$outPush);
        }

        json_ok(['output' => $outAdd . "\n" . $outCommit . "\n" . $outPush]);
        return;
    }

    // ── scope=file: commit specific files ────────────────────────────────
    if (trim($pathParam) === '') {
        throw new ValidationException('Path is empty.');
    }

    $paths = array_values(array_filter(
        array_map('trim', explode(',', $pathParam)),
        fn($s) => $s !== ''
    ));
    if (empty($paths)) {
        throw new ValidationException('No valid paths.');
    }

    $groups   = [];
    $notFound = [];
    $invalid  = [];

    foreach ($paths as $p) {
        $abs = $builder->getAbsoluteFilePath($p, $repository);

        if (empty($abs) || !file_exists($abs)) {
            $notFound[] = $p;
            continue;
        }

        $info = $builder->get_git_info($abs);
        if (empty($info) || empty($info['ok'])) {
            $invalid[] = $p;
            continue;
        }

        $top = $info['toplevel'];
        $rel = $info['relpath'];
        if (!isset($groups[$top])) {
            $groups[$top] = ['rel' => []];
        }
        $groups[$top]['rel'][$rel] = true;
    }

    if (!empty($notFound)) {
        throw new NotFoundException('Source file not found: ' . implode(', ', $notFound));
    }
    if (!empty($invalid)) {
        throw new ValidationException('Not a valid git path: ' . implode(', ', $invalid));
    }
    if (empty($groups)) {
        throw new ValidationException('No valid git-tracked paths to commit.');
    }

    $outputs = [];

    foreach ($groups as $toplevel => $g) {
        $relpaths = array_keys($g['rel']);
        if (empty($relpaths)) continue;

        $args   = implode(' ', array_map('escapeshellarg', $relpaths));
        $outAdd = '';
        if (!$builder->execGitCommand($toplevel, 'add -- ' . $args, $outAdd)) {
            throw new ValidationException('git add failed: ' . (string)$outAdd);
        }

        $outCommit = '';
        $commitOk  = $builder->execGitCommand($toplevel, 'commit -m ' . escapeshellarg($message), $outCommit);
        if (!$commitOk) {
            $trimmed = trim((string)$outCommit);
            if (stripos($trimmed, 'nothing to commit') === false) {
                throw new ValidationException('git commit failed: ' . $trimmed);
            }
        }

        $outPush = '';
        if (!$builder->execGitCommand($toplevel, 'push', $outPush)) {
            throw new ValidationException('git push failed: ' . (string)$outPush);
        }

        $outputs[] = 'Repo: ' . $toplevel . "\n" . $outAdd . "\n" . $outCommit . "\n" . $outPush;
    }

    json_ok(['output' => implode("\n\n", $outputs)]);
}
