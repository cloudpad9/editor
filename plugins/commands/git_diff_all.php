<?php
function git_diff_all($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $path       = \CloudPad\Core\Request::getString('path');

    $filepath = $builder->getAbsoluteFilePath($path, $repository);
    if (empty($filepath) || !file_exists($filepath)) {
        json_fail('Source file not found.');
    }

    $info = $builder->get_git_info($filepath);
    if (empty($info) || empty($info['ok'])) {
        $msg = $info['message'] ?? 'Not a valid git repository root.';
        json_fail($msg, ['debug' => $info]);
    }

    $toplevel = $info['toplevel'];

    $out = '';
    $ok  = $builder->execGitCommand($toplevel, '--no-pager diff --no-color --', $out);

    // git diff returns exit code 1 when differences exist — not an error
    if (!$ok && trim((string)$out) === '') {
        json_fail('git diff failed: ' . (string)$out);
    }

    json_ok(['output' => (string)$out]);
}
