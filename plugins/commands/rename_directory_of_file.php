<?php
function rename_directory_of_file($builder) {
    $filename = \CloudPad\Core\Request::getString('filename');
    $repository = \CloudPad\Core\Request::getString('repository');
    $newname = \CloudPad\Core\Request::getString('newname');

    $filepath = $builder->getAbsoluteFilePath($filename, $repository);

    if (empty($filepath)) {
        \CloudPad\Core\Response::json(array('success' => false, 'message' => 'Source file not found.'));

        return;
    }

    $filename = basename($filepath);
    $directoryPath = dirname($filepath);
    $newDirectoryPath = dirname($directoryPath).'/'.$newname;
    $newFilePath = $newDirectoryPath.'/'.$filename;

    if (file_exists($newDirectoryPath)) {
        \CloudPad\Core\Response::json(array('success' => false, 'message' => "Destination directory '$newname' already exists."));

        return;
    }

    if (!rename($directoryPath, $newDirectoryPath)) {
        \CloudPad\Core\Response::json(array('success' => false, 'message' => "Cannot rename"));

        return;
    }

    $rpath = $builder->getRepositoryWisePath($newFilePath, $repository, $filename);

    $builder->setFilePath($rpath, $newFilePath, $repository);
    $builder->addToRepositoryFilePaths($newFilePath, $repository);

    $content = $builder->file_get_contents($newFilePath, $repository);

    \CloudPad\Core\Response::json(array('success' => true, 'content' => $content, 'filename' => $rpath, 'repository' => $repository));
}
