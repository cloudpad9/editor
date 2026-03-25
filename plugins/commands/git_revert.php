<?php
function git_revert($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $pathParam  = \CloudPad\Core\Request::getString('path');
    $commit     = trim(\CloudPad\Core\Request::getString('commit'));

    if (trim($pathParam) === '') {
        json_fail('Path is empty.');
    }
    if ($commit !== '' && !preg_match('/^[a-f0-9]{6,40}$/i', $commit)) {
        json_fail('Invalid commit hash format.');
    }

    $paths = array_values(array_filter(
        array_map('trim', explode(',', (string)$pathParam)),
        fn($s) => $s !== ''
    ));
    if (!$paths) {
        json_fail('No valid paths.');
    }

    $groups  = [];
    $invalid = [];

    foreach ($paths as $p) {
        $abs = $builder->getAbsoluteFilePath($p, $repository);
        if (!$abs) {
            // Accept absolute system paths
            if (preg_match('~^/|^[A-Za-z]:[\\\\/]~', $p)) {
                $abs = $p;
            }
        }
        if (!$abs) { $invalid[] = $p; continue; }

        // Walk up to find existing ancestor (file may have been deleted)
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
        json_fail('No valid git-tracked paths to revert. Invalid: ' . implode(', ', $invalid));
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
            json_fail('git checkout failed: ' . (string)$outCheckout);
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
