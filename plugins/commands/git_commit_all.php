<?php
function git_commit_all($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $path       = \CloudPad\Core\Request::getString('path');
    $message    = \CloudPad\Core\Request::getString('message');  // fix: was $_REQUEST['path']

    if (empty($message)) {
        $message = 'Update all files';
    }

    $filepath = $builder->getAbsoluteFilePath($path, $repository);
    if (empty($filepath)) {
        json_fail('Source file not found.');
    }

    $info = $builder->get_git_info($filepath);
    if (empty($info) || empty($info['ok'])) {
        $msg = $info['message'] ?? 'Not a valid git repository root.';
        json_fail($msg, ['debug' => $info]);
    }

    $toplevel = $info['toplevel'];

    $outAdd = '';
    if (!$builder->execGitCommand($toplevel, 'add .', $outAdd)) {
        json_fail('git add failed: ' . (string)$outAdd);
    }

    $outCommit = '';
    $commitOk  = $builder->execGitCommand($toplevel, 'commit -m ' . escapeshellarg($message), $outCommit);

    if (!$commitOk) {
        $trimmed = trim((string)$outCommit);
        if (stripos($trimmed, 'nothing to commit') !== false) {
            json_ok(['message' => 'Nothing to commit (working tree clean).', 'output' => $outAdd . "\n" . $outCommit]);
        } else {
            json_fail('git commit failed: ' . $trimmed);
        }
    }

    $outPush = '';
    if (!$builder->execGitCommand($toplevel, 'push', $outPush)) {
        json_fail('git push failed: ' . (string)$outPush);
    }

    json_ok(['output' => $outAdd . "\n" . $outCommit . "\n" . $outPush]);
}
