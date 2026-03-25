<?php
function rename_directory($builder) {
    $filename = \CloudPad\Core\Request::getString('filename');
    $repository = \CloudPad\Core\Request::getString('repository');
    $newname = \CloudPad\Core\Request::getString('newname');

    $filepath = $builder->getAbsoluteFilePath($filename, $repository);

    if (empty($filepath)) {
        \CloudPad\Core\Response::json(array('success' => false, 'message' => 'Source file not found.'));

        return;
    }

    $newfilepath = dirname($filepath).'/'.$newname;

    if (file_exists($newfilepath)) {
        \CloudPad\Core\Response::json(array('success' => false, 'message' => "Destination file '$newname' already exists."));

        return;
    }

    $builder->try_exec('mv ' . escapeshellarg($filepath) . ' ' . escapeshellarg($newfilepath));

    if (!file_exists($newfilepath)) {
        \CloudPad\Core\Response::json(array('success' => false, 'message' => "Cannot rename $filepath -> $newfilepath"));

        return;
    }

    $rpath = $builder->getRepositoryWisePath($newfilepath, $repository, $newname);

    \CloudPad\Core\Response::json(array('success' => true, 'filename' => $rpath, 'repository' => $repository));
}
