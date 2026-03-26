<?php
namespace CloudPad\Editor;

/**
 * EditorService — Editor-level operations cho CloudPad.
 *
 * Tách từ Builder các methods liên quan đến editor workflow:
 *   openFileByName, getDirectoryChildren, getDirectoryStructure,
 *   addToDirectoryStructure, fileLiveSearch, getFileContent,
 *   closeFile, getUserUploadFiles, uploadFile, downloadUserFile,
 *   deleteUserFile, getUserTempFilePaths, getNewFilePath, newTempFile,
 *   cloneFile, rebuildSubIndexes, saveCurrentFile,
 *   convertTabsToWhitespaces, trimTrailingWhitespaces, trimTrailingCommas,
 *   onBeforeSavingFile, isSameContent,
 *   setFilePath, addToRepositoryFilePaths, getOpenFiles, getEditorOpenFiles
 *
 * Phase 3 (gap fill) — 13 public methods + helpers.
 *
 * Pattern: Builder giữ thin wrappers, delegate sang đây.
 */
class EditorService implements EditorServiceInterface
{
    private \CloudPad\Repository\RepositoryManagerInterface  $repoManager;
    private \CloudPad\FileSystem\FileOperationsInterface      $fileOps;
    private \CloudPad\Search\FileSearchServiceInterface       $fileSearch;
    private \CloudPad\Auth\AuthServiceInterface               $auth;
    private \CloudPad\Editor\ColorManager                     $colorManager;
    private \CloudPad\Editor\RevisionManager                  $revisionManager;
    private \CloudPad\Core\Output\OutputManager               $output;

    public function __construct(
        \CloudPad\Repository\RepositoryManagerInterface  $repoManager,
        \CloudPad\FileSystem\FileOperationsInterface      $fileOps,
        \CloudPad\Search\FileSearchServiceInterface       $fileSearch,
        \CloudPad\Auth\AuthServiceInterface               $auth,
        \CloudPad\Editor\ColorManager                     $colorManager,
        \CloudPad\Editor\RevisionManager                  $revisionManager,
        \CloudPad\Core\Output\OutputManager               $output
    ) {
        $this->repoManager     = $repoManager;
        $this->fileOps         = $fileOps;
        $this->fileSearch      = $fileSearch;
        $this->auth            = $auth;
        $this->colorManager    = $colorManager;
        $this->revisionManager = $revisionManager;
        $this->output          = $output;
    }

    // ── File open / navigation ────────────────────────────────────────────────

    /**
     * Tìm file theo tên trong repository, set session path, trả JSON.
     */
    public function openFileByName(string $filename, bool $fromcache, string $repository): void
    {
        global $ajax;

        $filepath = $this->fileSearch->searchForFile($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(['success' => false, 'message' => "File not found: $filename"]);
            return;
        }

        $this->setFilePath($filename, $filepath, $repository);

        if ($ajax) {
            $content = $this->fileOps->fileGetContents($filepath, $repository);
            $color   = $this->colorManager->getColor($filepath);
            $rpath   = $this->repoManager->getRepositoryWisePath($filepath, $repository, $filename);

            \CloudPad\Core\Response::json([
                'success'    => true,
                'content'    => $content,
                'filename'   => $rpath,
                'repository' => $repository,
                'color'      => $color,
            ]);
        }
    }

    /**
     * Trả danh sách children của một path trong repository (branch://relpath).
     */
    public function getDirectoryChildren(string $repository, string $path): void
    {
        $children = [];
        $settings = $this->repoManager->getRepositorySettings($repository);
        $dirs     = $settings['dirs'];

        $branch = '';
        if (preg_match('/^(.+):\/\/(.*)/', trim($path), $match)) {
            $branch = $match[1];
            $path   = $match[2];
        }

        if (empty($branch) && empty($path)) {
            // Trả các dirs gốc của repository
            foreach ($dirs as $index => $dir) {
                $children[] = [
                    'name'       => basename($dir),
                    'repository' => $repository,
                    'path'       => ($index + 1) . '://',
                    'isDir'      => is_dir($dir),
                    'children'   => [],
                ];
            }
        } elseif ($branch === 'quick-access') {
            $items = $_SESSION['quick-access'] ?? [];
            foreach ($items as $item) {
                if (isset($item['repository'])) {
                    $children[] = [
                        'name'        => $item['name'],
                        'repository'  => $item['repository'],
                        'path'        => $item['path'],
                        'isDir'       => true,
                        'quickAccess' => true,
                        'children'    => [],
                    ];
                }
            }
        } elseif (is_numeric($branch)) {
            $branchDir = $dirs[$branch - 1] ?? '';

            if (!empty($branchDir) && is_dir($branchDir)) {
                $branchDir = rtrim($branchDir, '/');
                $dir       = $branchDir . '/' . $path;

                if (is_dir($dir)) {
                    $iterator = new \DirectoryIterator($dir);
                    foreach ($iterator as $entry) {
                        $name    = $entry->getFilename();
                        $epath   = $entry->getPathname();
                        $epath   = str_replace($branchDir . '/', $branch . '://', $epath);

                        if ($entry->isFile()) {
                            $children[] = ['name' => $name, 'repository' => $repository, 'path' => $epath, 'isFile' => true, 'children' => []];
                        } elseif ($entry->isDir() && !$entry->isDot()) {
                            $children[] = ['name' => $name, 'repository' => $repository, 'path' => $epath, 'isDir'  => true, 'children' => []];
                        }
                    }
                }
            }
        } else {
            \CloudPad\Core\Response::json(['success' => false, 'message' => 'Unknown path']);
        }

        \CloudPad\Core\Response::json(['success' => true, 'children' => $children]);
    }

    /**
     * Trả cây thư mục dạng nested array cho toàn bộ repository.
     */
    public function getDirectoryStructure(string $repository): void
    {
        $settings = $this->repoManager->getRepositorySettings($repository);
        $dirs     = $settings['dirs'];

        $filepaths = $this->repoManager->getRepositoryFilePaths($repository, false);

        foreach ($dirs as $dir) {
            foreach ($filepaths as &$filepath) {
                if (stripos($filepath, $dir) === 0) {
                    $filepath = str_replace($dir, '', $filepath);
                }
            }
        }

        $directoryStructure = $this->buildDirectoryStructure($filepaths);
        \CloudPad\Core\Response::json(['success' => true, 'directoryStructure' => $directoryStructure]);
    }

    /**
     * Convert flat filepath list thành nested tree structure.
     */
    public function buildDirectoryStructure(array $filepaths): array
    {
        $result = [];
        foreach ($filepaths as $filepath) {
            $parts = explode('/', trim($filepath, '/'));
            $this->addToDirectoryStructure($result, $parts, '');
        }
        return $result;
    }

    /**
     * Helper đệ quy cho buildDirectoryStructure.
     */
    public function addToDirectoryStructure(array &$structure, array $parts, string $basePath): void
    {
        if (empty($parts)) {
            return;
        }

        $part  = array_shift($parts);
        $found = false;

        foreach ($structure as &$item) {
            if ($item['name'] === $part) {
                $found = true;
                $this->addToDirectoryStructure($item['children'], $parts, $item['path']);
                break;
            }
        }

        if (!$found) {
            $itemPath = $basePath . '/' . $part;
            $newItem  = ['name' => $part, 'children' => [], 'path' => $itemPath];

            if (empty($parts)) {
                $newItem['isFile'] = true;
            } else {
                $this->addToDirectoryStructure($newItem['children'], $parts, $itemPath);
            }

            $structure[] = $newItem;
        }
    }

    /**
     * Live search file, trả HTML <ul> list (legacy streaming HTML response).
     */
    public function fileLiveSearch(string $repository, string $filename): void
    {
        $settings = $this->repoManager->getRepositorySettings($repository);
        $dirs     = $settings['dirs'];

        if (!empty($filename) && $filename[0] === '/' && file_exists($filename)) {
            $filepaths = [$filename];
        } else {
            $recentfilepaths = $_SESSION['recentfilepaths'][$repository] ?? [];
            $filepaths_1     = !empty($recentfilepaths)
                ? $this->fileSearch->searchForFilesInArray($filename, $recentfilepaths, 20)
                : [];

            if (strlen($filename) < 3 && !empty($filepaths_1)) {
                $filepaths = $filepaths_1;
            } else {
                $filepaths_2 = $this->fileSearch->searchForFiles($filename, $repository, 20);
                $filepaths   = array_merge($filepaths_1, $filepaths_2);
            }
        }

        $relpaths = [];
        foreach ($filepaths as $filepath) {
            $relpath = $filepath;
            foreach ($dirs as $dir) {
                if (stripos($filepath, $dir) === 0) {
                    $relpath = str_replace($dir, '', $filepath);
                    break;
                }
            }
            $relpaths[$filepath] = $relpath;
        }

        uasort($relpaths, function ($a, $b) {
            return strlen($a) - strlen($b);
        });

        $html = '<ul class="live-search-results">';
        foreach ($relpaths as $filepath => $relpath) {
            $html .= '<li><span>' . htmlspecialchars($relpath, ENT_QUOTES) . '</span></li>';
        }
        $html .= '</ul>';

        echo $html;
    }

    /**
     * Lấy nội dung file, trả JSON.
     */
    public function getFileContent(string $filename, string $repository = ''): void
    {
        if (!$this->repoManager->hasRepositoryPermission($repository)) {
            \CloudPad\Core\Response::json(['success' => false, 'message' => "File not found: $filename"]);
            return;
        }

        $filepath = $this->repoManager->getAbsoluteFilePath($filename, $repository);

        if (!empty($filepath)) {
            $this->setFilePath($filename, $filepath, $repository);

            $content = $this->fileOps->fileGetContents($filepath, $repository);
            $color   = $this->colorManager->getColor($filepath);
            $rpath   = $this->repoManager->getRepositoryWisePath($filepath, $repository, $filename);

            \CloudPad\Core\Response::json([
                'success'    => true,
                'content'    => $content,
                'filename'   => $rpath,
                'repository' => $repository,
                'color'      => $color,
            ]);
        } else {
            if (!empty($repository)) {
                $this->openFileByName($filename, false, $repository);
            }
        }
    }

    /**
     * Đóng file trong session; xoá temp file nếu là temp file.
     */
    public function closeFile(string $filename, string $repository, bool $standalone = false): void
    {
        if ($standalone) {
            return;
        }

        if (isset($_SESSION['openfilepaths'][$repository][$filename])) {
            $filepath     = $_SESSION['openfilepaths'][$repository][$filename];
            $is_temp_file = ($filename[0] === '*');

            if ($is_temp_file) {
                unlink($filepath);
            }

            unset($_SESSION['openfilepaths'][$repository][$filename]);
        }
    }

    // ── User file upload/download ─────────────────────────────────────────────

    /**
     * Trả danh sách tên file trong user upload dir.
     */
    public function getUserUploadFiles(): array
    {
        $dir   = $this->auth->getUserUploadDir();
        $paths = $this->fileSearch->rsearch($dir);

        foreach ($paths as $i => $path) {
            $paths[$i] = basename($path);
        }

        return $paths;
    }

    /**
     * Xử lý upload file từ $_FILES, trả JSON.
     */
    public function uploadFile(): void
    {
        $uploaddir    = \CloudPad\Core\Request::getString('directory');
        $is_custom_dir = !empty($uploaddir) && is_dir($uploaddir);

        if (!$is_custom_dir) {
            $uploaddir = $this->auth->getUserUploadDir();
        } else {
            $_SESSION['files.upload.directory'] = $uploaddir;
        }

        if (!is_writable($uploaddir)) {
            \CloudPad\Core\Response::fail('Upload directory is not writable');
        }

        foreach ($_FILES as $file) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

            if (!preg_match('/^(rar|zip|exe|pdf|doc|docx|html|xml|xls|xlsx|csv|svg|png|gif|jpg|json|mp4|webp)$/is', $ext)) {
                \CloudPad\Core\Response::fail("Uploading `.$ext' files is not allowed");
            }

            if (move_uploaded_file($file['tmp_name'], $uploaddir . '/' . basename($file['name']))) {
                $files[] = $uploaddir . '/' . $file['name'];
            } else {
                \CloudPad\Core\Response::json(['success' => false, 'message' => 'Upload failed']);
                return;
            }
        }

        \CloudPad\Core\Response::json(['success' => true]);
    }

    /**
     * Trigger download file từ user upload dir.
     */
    public function downloadUserFile(string $filename): void
    {
        $filename = basename(ltrim($filename, '/'));

        if (empty($filename)) {
            \CloudPad\Core\Response::fail('Invalid file name.');
        }

        $dir      = $this->auth->getUserUploadDir();
        $file     = $dir . '/' . $filename;
        $realFile = realpath($file);
        $realDir  = realpath($dir);

        if ($realFile === false || strncmp($realFile, $realDir . '/', strlen($realDir) + 1) !== 0) {
            \CloudPad\Core\Response::fail("File unreadable: $filename");
        }

        if (!is_file($realFile)) {
            \CloudPad\Core\Response::fail("File not found: $filename");
        }

        $ext = pathinfo($realFile, PATHINFO_EXTENSION);
        if (!preg_match('/^(rar|zip|exe|pdf|doc|docx|xml|xls|xlsx|html|csv|svg|png|gif|jpg|gz|sql|mp4|webp)$/is', $ext)) {
            \CloudPad\Core\Response::fail("Downloading `.$ext' files is not allowed");
        }

        \CloudPad\Core\Response::download($realFile, $filename);
    }

    /**
     * Xoá file trong user upload dir.
     */
    public function deleteUserFile(string $filename): void
    {
        $filename = basename(ltrim($filename, '/'));

        if (empty($filename)) {
            \CloudPad\Core\Response::fail('Invalid file name.');
        }

        $dir      = $this->auth->getUserUploadDir();
        $file     = $dir . '/' . $filename;
        $realFile = realpath($file);
        $realDir  = realpath($dir);

        if ($realFile === false || strncmp($realFile, $realDir . '/', strlen($realDir) + 1) !== 0) {
            \CloudPad\Core\Response::fail("Access denied: $filename");
        }

        if (!is_file($realFile)) {
            \CloudPad\Core\Response::fail("File not found: $filename");
        }

        unlink($realFile);
        \CloudPad\Core\Response::ok();
    }

    // ── Temp files ────────────────────────────────────────────────────────────

    /**
     * Trả danh sách paths của temp files trong user temp dir, sorted by index.
     */
    public function getUserTempFilePaths(): array
    {
        $dir       = $this->auth->getUserTempDir();
        $filepaths = glob($dir . '/new *');
        $tmp       = [];

        foreach ($filepaths as $filepath) {
            if (preg_match('/new ([0-9]+)/', $filepath, $match)) {
                $tmp[$filepath] = (int) $match[1];
            }
        }

        asort($tmp);
        return array_keys($tmp);
    }

    /**
     * Tính path cho temp file mới tiếp theo (tên như "new 1", "new 2", ...).
     */
    public function getNewFilePath(): string
    {
        $paths = $this->getUserTempFilePaths();
        $files = array_map('basename', $paths);
        $len   = count($files);
        $name  = '';

        for ($i = 1; $i <= $len * 2 + 1; $i++) {
            $name = 'new ' . $i;
            if (!in_array($name, $files)) {
                break;
            }
        }

        if (empty($name)) {
            $name = 'new 1';
        }

        return $this->auth->getUserTempDir() . '/' . $name;
    }

    /**
     * Tạo temp file mới, ghi session, trả JSON.
     */
    public function newTempFile(): void
    {
        $newfilepath = $this->getNewFilePath();
        $this->fileOps->filePutContents($newfilepath, '');

        if (!file_exists($newfilepath)) {
            \CloudPad\Core\Response::json(['success' => false, 'message' => "Unable to create $newfilepath."]);
            return;
        }

        $filename = basename($newfilepath);
        $rpath    = '*' . $filename;

        $_SESSION['filepaths']['*'][$rpath]     = $newfilepath;
        $_SESSION['openfilepaths']['*'][$rpath] = $newfilepath;

        \CloudPad\Core\Response::json([
            'success'    => true,
            'content'    => '',
            'filename'   => $rpath,
            'repository' => '*',
        ]);
    }

    /**
     * Clone (copy) file trong cùng repository.
     */
    public function cloneFile(string $filename, string $repository, string $newname): void
    {
        $filepath = $this->repoManager->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(['success' => false, 'message' => 'Source file not found.']);
            return;
        }

        $newfilepath = dirname($filepath) . '/' . $newname;

        if ($this->fileOps->fileExists($newfilepath, $repository)) {
            \CloudPad\Core\Response::json(['success' => false, 'message' => "Destination file '$newname' already exists."]);
            return;
        }

        $content = $this->fileOps->fileGetContents($filepath, $repository);
        $this->fileOps->filePutContents($newfilepath, $content, $repository);

        if (!$this->fileOps->fileExists($newfilepath, $repository)) {
            \CloudPad\Core\Response::json(['success' => false, 'message' => "Unable to create $newfilepath."]);
            return;
        }

        $rpath = $this->repoManager->getRepositoryWisePath($newfilepath, $repository, $newname);
        $this->setFilePath($rpath, $newfilepath, $repository);
        $this->addToRepositoryFilePaths($newfilepath, $repository);

        \CloudPad\Core\Response::json([
            'success'    => true,
            'content'    => $content,
            'filename'   => $rpath,
            'repository' => $repository,
        ]);
    }

    /**
     * Rebuild sub-indexes cho directory chứa filepath, trả JSON.
     */
    public function rebuildSubIndexes(string $filename, string $repository): void
    {
        $filepath = $this->repoManager->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath) || !file_exists($filepath)) {
            \CloudPad\Core\Response::json(['success' => false, 'message' => 'Source file not found.']);
            return;
        }

        $dir       = dirname($filepath);
        $cachefile = $this->repoManager->getRepositoryCacheFile($repository);
        $filepaths = json_decode($this->fileOps->fileGetContents($cachefile), true) ?: [];
        $paths     = $this->fileSearch->rsearch($dir);

        if (!empty($paths)) {
            $filepaths = array_merge($filepaths, $paths);
        }

        $this->fileOps->filePutContents($cachefile, json_encode($filepaths));
        \CloudPad\Core\Response::json(['success' => true, 'message' => 'Indexing done']);
    }

    // ── Save file ─────────────────────────────────────────────────────────────

    /**
     * Lưu nội dung file hiện tại (với safety checks + revision).
     */
    public function saveCurrentFile(
        string $filename,
        string $repository,
        string $content,
        bool $createTempRevisionOnly
    ): void {
        $filepath = $this->repoManager->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(['success' => false, 'message' => 'Source file not found.']);
            return;
        }

        $old_content = $this->fileOps->fileGetContents($filepath, $repository);

        // Safety: kiểm tra content không bị nhầm file
        $segmentsize     = 500;
        $content_segment = substr($content, 0, $segmentsize);
        $file_segment    = substr($old_content, 0, $segmentsize);
        $is_temp_file    = ($filename[0] === '*');
        $not_temp_content = stripos($content, '<?php') !== false
            || stripos($content, '<{$smarty') !== false;

        if ($is_temp_file && $not_temp_content) {
            $this->output->flushLine("[ERROR] Saving a wrong content to '$filename', possibly", true);
            return;
        }

        $protection = stripos($filepath, '/builder/') === 0
            && !preg_match('/^\/builder\/(config|apps|tmp)/is', $filepath);

        if ($protection && !$this->isSameContent($content_segment, $file_segment)) {
            $this->output->flushLine("[ERROR] $filepath Save to a wrong file '" . basename($filepath) . "'???", true);
            return;
        }

        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        if (trim($content) !== '') {
            if ($content !== $old_content) {
                if ($createTempRevisionOnly) {
                    $this->revisionManager->createTempRevision($filepath, $content);
                } else {
                    $this->revisionManager->saveFileRevision($filepath, $old_content);
                    $this->onBeforeSavingFile($content, $extension);
                    $this->fileOps->filePutContents($filepath, $content, $repository);
                    $_SESSION['openfilepaths'][$repository][$filename] = $filepath;
                }
            }
        } else {
            $this->output->flushLine("[ERROR] Cannot save an empty content to '" . basename($filepath) . "'", true);
        }
    }

    // ── Content transformations ───────────────────────────────────────────────

    public function convertTabsToWhitespaces(string $content): string
    {
        return str_replace("\t", '    ', $content);
    }

    public function trimTrailingWhitespaces(string $content): string
    {
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            $lines[$i] = rtrim($line);
        }
        return trim(implode("\n", $lines));
    }

    public function trimTrailingCommas(string $content): string
    {
        return preg_replace_callback(
            '/,(\s+)(\]|\}|\))/s',
            function ($matches) {
                return $matches[1] . $matches[2];
            },
            $content
        );
    }

    /**
     * Pre-save hook: tabs, trailing whitespace, trailing commas, trailing newline.
     */
    public function onBeforeSavingFile(string &$content, string $extension): void
    {
        $is_web_file = in_array($extension, ['tpl', 'php', 'js', 'vue', 'html', 'css']);

        if ($is_web_file) {
            $content = $this->convertTabsToWhitespaces($content);
        }

        $content = $this->trimTrailingWhitespaces($content);

        if ($is_web_file) {
            $content = $this->trimTrailingCommas($content);
            $content = $content . "\n";
        }
    }

    /**
     * So sánh hai content segment (strip whitespace/newline trước khi so sánh).
     */
    public function isSameContent(string $content1, string $content2): bool
    {
        $content1 = preg_replace('/^\s+|\n|\r|\s+$/m', '', $content1);
        $content2 = preg_replace('/^\s+|\n|\r|\s+$/m', '', $content2);

        $size     = min(strlen($content1), strlen($content2));
        $content1 = substr($content1, 0, $size);
        $content2 = substr($content2, 0, $size);

        if ($content1 !== $content2) {
            // giữ legacy debug output để không break behavior
            echo "content1 = $content1<br/>";
            echo "content2 = $content2<br/>";
        }

        return $content1 === $content2;
    }

    // ── Session / file path registry ─────────────────────────────────────────

    /**
     * Ghi filepath vào các session buckets (filepaths, openfilepaths, recentfilepaths).
     */
    public function setFilePath(string $filename, string $filepath, string $repository): void
    {
        $_SESSION['filepaths'][$repository][$filename]       = $filepath;
        $_SESSION['openfilepaths'][$repository][$filename]   = $filepath;
        $_SESSION['recentfilepaths'][$repository][$filename] = $filepath;
    }

    /**
     * Thêm filepath vào cache file của repository.
     */
    public function addToRepositoryFilePaths(string $filepath, string $repository): void
    {
        $cachefile = dirname(dirname(dirname(__DIR__))) . '/cache/' . $repository;

        if (file_exists($cachefile)) {
            $filepaths   = json_decode($this->fileOps->fileGetContents($cachefile), true) ?: [];
            $filepaths[] = $filepath;
            $this->fileOps->filePutContents($cachefile, json_encode($filepaths));
        }
    }

    /**
     * Trả tất cả open files (merge temp + session openfilepaths).
     */
    public function getOpenFiles(bool $tempFilesOnly): array
    {
        $paths     = [];
        $tmp_paths = $this->getUserTempFilePaths();

        foreach ($tmp_paths as $path) {
            $name                          = '*' . basename($path);
            $paths['*'][$name]             = $path;
            $_SESSION['filepaths']['*'][$name] = $path;
        }

        if (!$tempFilesOnly) {
            $open_paths = (isset($_SESSION['openfilepaths']) && is_array($_SESSION['openfilepaths']))
                ? $_SESSION['openfilepaths']
                : [];

            foreach ($open_paths as $repo => $repo_files) {
                foreach ($repo_files as $name => $path) {
                    $paths[$repo][$name] = $path;
                }
            }

            $_SESSION['openfilepaths'] = $paths;
        }

        return $paths;
    }

    /**
     * Giống getOpenFiles nhưng trả array of filenames thay vì path map.
     */
    public function getEditorOpenFiles(bool $tempFilesOnly = false): array
    {
        $files = $this->getOpenFiles($tempFilesOnly);

        foreach ($files as $repo => $repo_files) {
            $files[$repo] = array_keys($repo_files);
        }

        return $files;
    }
}
