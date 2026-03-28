<?php
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * rename_file — Đổi tên file trong repository.
 * Phase 6.3: Response::json(fail) → throw exceptions
 */
function rename_file($builder) {
    $filename   = \CloudPad\Core\Request::getString('filename');
    $repository = \CloudPad\Core\Request::getString('repository');
    $newname    = \CloudPad\Core\Request::getString('newname');

    $filepath = $builder->getAbsoluteFilePath($filename, $repository);
    if (empty($filepath)) {
        throw new NotFoundException('Source file not found.');
    }

    $newfilepath = dirname($filepath) . '/' . $newname;

    if (file_exists($newfilepath)) {
        throw new ValidationException("Destination file '$newname' already exists.");
    }

    $builder->try_exec('mv ' . escapeshellarg($filepath) . ' ' . escapeshellarg($newfilepath));

    if (!file_exists($newfilepath)) {
        throw new FileSystemException("Cannot rename $filepath -> $newfilepath.");
    }

    $rpath = $builder->getRepoManager()->getRepositoryWisePath($newfilepath, $repository, $newname);
    $builder->getEditorService()->setFilePath($rpath, $newfilepath, $repository);
    $builder->getEditorService()->addToRepositoryFilePaths($newfilepath, $repository);

    $content = $builder->file_get_contents($newfilepath, $repository);

    json_ok(['content' => $content, 'filename' => $rpath, 'repository' => $repository]);
}
