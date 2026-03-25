<?php
function git_commit($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $pathParam  = \CloudPad\Core\Request::getString('path');
    $message    = \CloudPad\Core\Request::getString('message');  // fix: was $_REQUEST['path']

    if (empty($message)) {
        $message = 'Update file';
    }

    if (trim($pathParam) === '') {
        json_fail('Path is empty.');
    }

    $paths = array_values(array_filter(
        array_map('trim', explode(',', $pathParam)),
        fn($s) => $s !== ''
    ));
    if (empty($paths)) {
        json_fail('No valid paths.');
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
        json_fail('Source file not found: ' . implode(', ', $notFound));
    }
    if (!empty($invalid)) {
        json_fail('Not a valid git path: ' . implode(', ', $invalid));
    }
    if (empty($groups)) {
        json_fail('No valid git-tracked paths to commit.');
    }

    $outputs = [];

    foreach ($groups as $toplevel => $g) {
        $relpaths = array_keys($g['rel']);
        if (empty($relpaths)) continue;

        $args   = implode(' ', array_map('escapeshellarg', $relpaths));
        $outAdd = '';
        if (!$builder->execGitCommand($toplevel, 'add -- ' . $args, $outAdd)) {
            json_fail('git add failed: ' . (string)$outAdd);
        }

        $outCommit = '';
        $commitOk  = $builder->execGitCommand($toplevel, 'commit -m ' . escapeshellarg($message), $outCommit);
        if (!$commitOk) {
            $trimmed = trim((string)$outCommit);
            if (stripos($trimmed, 'nothing to commit') === false) {
                json_fail('git commit failed: ' . $trimmed);
            }
        }

        $outPush = '';
        if (!$builder->execGitCommand($toplevel, 'push', $outPush)) {
            json_fail('git push failed: ' . (string)$outPush);
        }

        $outputs[] = 'Repo: ' . $toplevel . "\n" . $outAdd . "\n" . $outCommit . "\n" . $outPush;
    }

    json_ok(['output' => implode("\n\n", $outputs)]);
}
