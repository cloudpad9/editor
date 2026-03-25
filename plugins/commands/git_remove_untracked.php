<?php
function git_remove_untracked($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $path       = \CloudPad\Core\Request::getString('path');

    $filepath = $builder->getAbsoluteFilePath($path, $repository);
    if (empty($filepath) || !file_exists($filepath)) {
        json_fail('Source file not found.');
    }

    $info = $builder->get_git_info($filepath);
    if (empty($info) || empty($info['ok'])) {
        $msg = $info['message'] ?? 'Cannot determine repository root.';
        json_fail($msg, ['debug' => $info]);
    }

    $toplevel = $info['toplevel'];

    $filepath_real = realpath($filepath) ?: $filepath;

    // Ensure path is inside repo
    $prefix = rtrim($toplevel, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($filepath_real, $prefix) !== 0) {
        json_fail('File is not inside the repository root.');
    }

    $relpath = ltrim(substr($filepath_real, strlen($prefix)), DIRECTORY_SEPARATOR);
    if ($relpath === '') {
        $relpath = basename($filepath_real);
    }

    // Block .git
    if ($relpath === '.git' || strpos($relpath, '.git' . DIRECTORY_SEPARATOR) === 0) {
        json_fail('Refusing to operate inside .git.');
    }

    // Reject tracked files
    $outCheck  = '';
    $isTracked = $builder->execGitCommand($toplevel, 'ls-files --error-unmatch -- ' . escapeshellarg($relpath), $outCheck);
    if ($isTracked) {
        json_fail('Path is tracked by Git. Refusing to remove.');
    }

    $ok     = false;
    $errMsg = '';
    if (is_file($filepath_real) || is_link($filepath_real)) {
        $ok = @unlink($filepath_real);
        if (!$ok) $errMsg = 'Failed to remove file.';
    } elseif (is_dir($filepath_real)) {
        $ok = _rrmdir_recursive($filepath_real, $errMsg);
    } else {
        $ok = @unlink($filepath_real);
        if (!$ok) $errMsg = 'Unsupported file type.';
    }

    if (!$ok) {
        json_fail($errMsg !== '' ? $errMsg : 'Remove failed.');
    }

    json_ok(['message' => "Done. {$relpath} has been removed."]);
}

function _rrmdir_recursive($dir, &$err = '') {
    if (!is_dir($dir)) {
        $err = 'Directory not found.';
        return false;
    }

    $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($ri as $item) {
        if ($item->isDir()) {
            if (!@rmdir($item->getPathname())) {
                $err = 'Failed to remove subdir: ' . $item->getPathname();
                return false;
            }
        } else {
            if (!@unlink($item->getPathname())) {
                $err = 'Failed to remove file: ' . $item->getPathname();
                return false;
            }
        }
    }

    if (!@rmdir($dir)) {
        $err = 'Failed to remove directory: ' . $dir;
        return false;
    }

    return true;
}
