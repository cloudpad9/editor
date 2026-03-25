<?php
function git_log_all($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $path       = \CloudPad\Core\Request::getString('path');

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

    $out = '';
    $builder->execGitCommand($toplevel, '--no-pager log --stat -n 20', $out);

    json_ok(['output' => (string)$out]);
}
