<?php
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\ValidationException;

/**
 * git_revert — Revert file(s) về trạng thái HEAD hoặc commit cụ thể.
 * Phase 6.3: json_fail() → throw exceptions
 */
function git_revert($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $pathParam  = \CloudPad\Core\Request::getString('path');
    $commit     = trim(\CloudPad\Core\Request::getString('commit'));

    if (trim($pathParam) === '') {
        throw new ValidationException('Path is empty.');
    }
    if ($commit !== '' && !preg_match('/^[a-f0-9]{6,40}$/i', $commit)) {
        throw new ValidationException('Invalid commit hash format.');
    }

    $paths = array_values(array_filter(
        array_map('trim', explode(',', (string)$pathParam)),
        fn($s) => $s !== ''
    ));
    if (!$paths) {
        throw new ValidationException('No valid paths.');
    }

    $groups  = [];
    $invalid = [];

    foreach ($paths as $p) {
        $abs = $builder->getAbsoluteFilePath($p, $repository);
        if (!$abs) {
            if (preg_match('~^/|^[A-Za-z]:[\\\\/]~', $p)) {
                $abs = $p;
            }
        }
        if (!$abs) { $invalid[] = $p; continue; }

        // Walk up để tìm ancestor tồn tại (file có thể đã bị xoá)
        $probe = $abs;
        $last  = '';
        while (!file_exists($probe)) {
            $parent = dirname($probe);
            if ($parent === $probe || $parent === '' || $parent === $last) break;
            $last  = $probe;
            $probe = $parent;
        }
        if (!file_exists($probe)) { $invalid[] = $p; continue; }

        $info = $builder->get_git_info($probe);
        if (empty($info['ok']) || empty($info['toplevel'])) { $invalid[] = $p; continue; }

        $top  = rtrim(str_replace('\\', '/', $info['toplevel']), '/');
        $absN = str_replace('\\', '/', $abs);

        if (strpos($absN, $top . '/') === 0) {
            $rel = ltrim(substr($absN, strlen($top . '/')), '/');
        } else {
            $probeN = str_replace('\\', '/', $probe);
            if (strpos($probeN, $top . '/') !== 0) { $invalid[] = $p; continue; }
            $tail = ltrim(substr($absN, strlen($probeN)), '/');
            $base = ltrim(substr($probeN, strlen($top . '/')), '/');
            $rel  = ltrim($base . '/' . $tail, '/');
        }

        if ($rel === '') { $invalid[] = $p; continue; }
        $groups[$top][$rel] = true;
    }

    if ($invalid) {
        throw new NotFoundException('No valid git-tracked paths to revert. Invalid: ' . implode(', ', $invalid));
    }

    $chunks = [];
    foreach ($groups as $toplevel => $set) {
        $relpaths = array_keys($set);
        $args     = implode(' ', array_map('escapeshellarg', $relpaths));

        // Unstage (safe to ignore failure)
        $outReset = '';
        $builder->execGitCommand($toplevel, '--no-pager reset HEAD -- ' . $args, $outReset);

        // Checkout — restores deleted files too
        $cmd         = '--no-pager checkout ' . ($commit !== '' ? escapeshellarg($commit) . ' -- ' : '-- ') . $args;
        $outCheckout = '';
        if (!$builder->execGitCommand($toplevel, $cmd, $outCheckout)) {
            throw new ValidationException('git checkout failed: ' . (string)$outCheckout);
        }

        $chunk = "Repo: $toplevel";
        if (($t = trim((string)$outReset))    !== '') $chunk .= "\n[Unstage] "  . $t;
        if (($t = trim((string)$outCheckout)) !== '') $chunk .= "\n[Checkout] " . $t;
        $chunks[] = $chunk;
    }

    $msg = 'Done. ' . count($paths) . ' item(s) reverted' . ($commit ? " to commit $commit" : '') . '.';
    json_ok([
        'message' => $msg,
        'output'  => implode("\n\n", $chunks),
    ]);
}
