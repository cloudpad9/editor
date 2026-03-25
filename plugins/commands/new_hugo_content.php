<?php
function splitHugoPath($fullPath, &$contentDir, &$relativePath, &$projectDir) {
    if (strpos($fullPath, '/content/') === false) {
        return false;
    }

    // Tìm đường dẫn đến thư mục content Hugo
    $contentDir = preg_replace('/\/content\/.*/', '/content', $fullPath);

    // Tìm đường dẫn đến thư mục của Hugo project
    $projectDir = str_replace('/content', '', $contentDir);

    // Relative path đến tập tin Markdown
    $relativePath = str_replace($contentDir . '/', '', $fullPath);

    return true;
}

function new_hugo_content($builder) {
    $path = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');
    $name = \CloudPad\Core\Request::getString('name');

    $settings = $builder->getRepositorySettings($repository);
    $dirs = $settings['dirs'];

    // Extract branch, path
    $branch = '';

    if (preg_match('/^([0-9]+)\:\/\/(.*)/', trim($path), $match)) {
        $branch = $match[1];
        $path = $match[2];
    }

    // Lấy branch dir
    $branchDir = isset($dirs[$branch - 1])? $dirs[$branch - 1] : '';

    if (!empty($branchDir) && is_dir($branchDir)) {
        $branchDir = rtrim($branchDir, '/');

        $dir = $branchDir.'/'.$path;

        if (is_dir($dir)) {
            $newFilePath = rtrim($dir, '/').'/'.$name;

            if (file_exists($newFilePath)) {
                \CloudPad\Core\Response::json(array('success' => false, 'message' => "Destination file '$name' already exists."));

                return;
            }

            ob_start();

            $builder->try_exec('chmod 755 ' . escapeshellarg($dir));

            $output = ob_get_clean();

            if (!empty($output)) {
                $output = str_replace($branchDir, '', $output);

                \CloudPad\Core\Response::json(array('success' => false, 'message' => $output));

                return;
            }

            if (!splitHugoPath($newFilePath, $contentDir, $relativePath, $projectDir)) {
                \CloudPad\Core\Response::json(array('success' => false, 'message' => "Not a Hugo content directory: $path"));

                return;
            }

            ob_start();

            $builder->try_exec('cd ' . escapeshellarg($projectDir) . '; sudo hugo new ' . escapeshellarg($relativePath));

            $output = ob_get_clean();

            if (!empty($output)) {
                $output = str_replace($branchDir, '', $output);
            }

            if (!file_exists($newFilePath)) {
                \CloudPad\Core\Response::json(array('success' => false, 'message' => "Operation failed. Cannot create Hugo file '$relativePath'. $output"));

                return;
            }

            \CloudPad\Core\Response::json(array('success' => true));
            exit;
        }
    }

    \CloudPad\Core\Response::json(array('success' => false, 'message' => "Operation failed"));
}
