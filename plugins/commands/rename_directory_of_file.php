<?php
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * rename_directory_of_file — Đổi tên thư mục chứa file đang mở.
 * Phase 6.3: Response::json(fail) → throw exceptions
 */
function rename_directory_of_file($builder) {
    $filename   = \CloudPad\Core\Request::getString('filename');
    $repository = \CloudPad\Core\Request::getString('repository');
    $newname    = \CloudPad\Core\Request::getString('newname');

    $filepath = $builder->getAbsoluteFilePath($filename, $repository);
    if (empty($filepath)) {
        throw new NotFoundException('Source file not found.');
    }

    $fileBasename     = basename($filepath);
    $directoryPath    = dirname($filepath);
    $newDirectoryPath = dirname($directoryPath) . '/' . $newname;
    $newFilePath      = $newDirectoryPath . '/' . $fileBasename;

    if (file_exists($newDirectoryPath)) {
        throw new ValidationException("Destination directory '$newname' already exists.");
    }

    if (!rename($directoryPath, $newDirectoryPath)) {
        throw new FileSystemException('Cannot rename directory.');
    }

    $rpath = $builder->getRepoManager()->getRepositoryWisePath($newFilePath, $repository, $fileBasename);
    $builder->getEditorService()->setFilePath($rpath, $newFilePath, $repository);
    $builder->getEditorService()->addToRepositoryFilePaths($newFilePath, $repository);

    $content = $builder->file_get_contents($newFilePath, $repository);

    json_ok(['content' => $content, 'filename' => $rpath, 'repository' => $repository]);
}
