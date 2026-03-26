<?php
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\PermissionDeniedException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * git_remove_untracked — Xoá file/thư mục untracked khỏi working tree.
 * Phase 6.3: json_fail() → throw exceptions
 */
function git_remove_untracked($builder) {
    $repository = \CloudPad\Core\Request::getString('repository');
    $path       = \CloudPad\Core\Request::getString('path');

    $filepath = $builder->getAbsoluteFilePath($path, $repository);
    if (empty($filepath) || !file_exists($filepath)) {
        throw new NotFoundException('Source file not found.');
    }

    $info = $builder->get_git_info($filepath);
    if (empty($info) || empty($info['ok'])) {
        throw new ValidationException($info['message'] ?? 'Cannot determine repository root.');
    }

    $toplevel      = $info['toplevel'];
    $filepath_real = realpath($filepath) ?: $filepath;

    $prefix = rtrim($toplevel, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($filepath_real, $prefix) !== 0) {
        throw new PermissionDeniedException('File is not inside the repository root.');
    }

    $relpath = ltrim(substr($filepath_real, strlen($prefix)), DIRECTORY_SEPARATOR);
    if ($relpath === '') {
        $relpath = basename($filepath_real);
    }

    if ($relpath === '.git' || strpos($relpath, '.git' . DIRECTORY_SEPARATOR) === 0) {
        throw new PermissionDeniedException('Refusing to operate inside .git.');
    }

    // Từ chối nếu file đang được track
    $outCheck  = '';
    $isTracked = $builder->execGitCommand($toplevel, 'ls-files --error-unmatch -- ' . escapeshellarg($relpath), $outCheck);
    if ($isTracked) {
        throw new PermissionDeniedException('Path is tracked by Git. Refusing to remove.');
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
        throw new FileSystemException($errMsg !== '' ? $errMsg : 'Remove failed.');
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
