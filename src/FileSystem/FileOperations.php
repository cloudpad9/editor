<?php
namespace CloudPad\FileSystem;

/**
 * FileOperations — I/O layer dùng chung cho CloudPad.
 *
 * Tách từ Builder các methods filesystem thuần túy:
 *   getLocalizedPath, getRelPath, file_exists, rename,
 *   file_get_contents, file_put_contents, try_chmod, try_exec,
 *   is_empty_dir
 *
 * Phase 3 (gap fill) — 9 methods.
 *
 * Builder delegates sang đây; tất cả repo-aware calls vẫn đi qua Builder
 * để giữ backward-compat với plugin commands hiện tại.
 */
class FileOperations
{
    /** @var \Builder */
    private $builder;

    public function __construct(\Builder $builder)
    {
        $this->builder = $builder;
    }

    // ── Path resolution ───────────────────────────────────────────────────────

    /**
     * Trả localised path (SFTP prefix, remote mount, v.v.) nếu có handler.
     */
    public function getLocalizedPath(string $file, string $repository): string
    {
        if (!empty($repository)) {
            $settings = $this->builder->getRepositorySettings($repository);
            $handler  = $settings['handler'] ?? null;

            if (!empty($handler)) {
                $file = $handler->getLocalizedPath($settings, $file);
            }
        }

        return $file;
    }

    /**
     * Trả path tương đối so với các dirs trong repository settings.
     */
    public function getRelPath(string $filepath, string $repository): string
    {
        $settings = $this->builder->getRepositorySettings($repository);
        $dirs     = $settings['dirs'];

        foreach ($dirs as $dir) {
            if (stripos($filepath, $dir) === 0) {
                return substr($filepath, strlen($dir));
            }
        }

        return $filepath;
    }

    // ── Filesystem wrappers ───────────────────────────────────────────────────

    /**
     * Kiểm tra file tồn tại, có localize path theo repository.
     */
    public function fileExists(string $file, string $repository = ''): bool
    {
        $file = $this->getLocalizedPath($file, $repository);
        return file_exists($file);
    }

    /**
     * Đổi tên / move file, có localize path theo repository.
     */
    public function rename(string $file, string $newfile, string $repository = ''): bool
    {
        $file    = $this->getLocalizedPath($file, $repository);
        $newfile = $this->getLocalizedPath($newfile, $repository);
        return rename($file, $newfile);
    }

    /**
     * Đọc nội dung file, có localize path.
     *
     * Gọi Response::fail() và exit nếu file không đọc được.
     */
    public function fileGetContents(string $file, string $repository = ''): string
    {
        if (empty($file)) {
            \CloudPad\Core\Response::fail('Empty file path');
        }

        $file = $this->getLocalizedPath($file, $repository);

        if (!is_readable($file)) {
            \CloudPad\Core\Response::fail("File unreadable: $file");
        }

        return (string) file_get_contents($file);
    }

    /**
     * Ghi nội dung file, có localize path.
     * Tự động chmod + mkdir nếu cần.
     *
     * @param  string|null $message  Populated with user-facing error string on failure
     * @param  bool        $verbose  Có flush notice khi lưu thành công không
     * @return bool
     */
    public function filePutContents(
        string $file,
        string $content,
        string $repository = '',
        ?string &$message = null,
        bool $verbose = true
    ): bool {
        $message = null;
        $file    = $this->getLocalizedPath($file, $repository);

        // Thử chmod nếu không writable
        if (file_exists($file) && !is_writable($file)) {
            $this->tryChmod('777', $file);
        }

        if (file_exists($file) && !is_writable($file)) {
            $message = "File unwritable: $file<br/>&nbsp;<br/>HINTS:<br/>- chmod 777 $file<br/>- chcon -Rt httpd_sys_rw_content_t $file";
            $this->builder->flush_line("[ERROR] $message", true);
            return false;
        }

        // Đảm bảo thư mục cha tồn tại
        $dir = dirname($file);

        if (!empty($dir) && !is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                $message = "Cannot create directory: $dir";
                $this->builder->flush_line("[ERROR] $message\n", true);
                return false;
            }
        }

        if (is_dir($dir) && !is_writable($dir)) {
            $this->tryChmod('777', $dir);
        }

        if (!file_put_contents($file, $content)) {
            if (!file_exists($file) && !is_writable($dir)) {
                $message = "Cannot write to directory: $dir<br/>&nbsp;<br/>HINTS:<br/>- chmod 777 $dir<br/>- chcon -Rt httpd_sys_rw_content_t $dir";
            } else {
                $message = "Cannot write to file: $file<br/>&nbsp;<br/>HINTS:<br/>- chmod 777 $file<br/>- chcon -Rt httpd_sys_rw_content_t $file";
            }
            $this->builder->flush_line("[ERROR] $message\n", true);
            return false;
        }

        if ($verbose) {
            $this->builder->flush_line(
                "[NOTICE] File '" . $file . "' saved.\n",
                true,
                "File $file saved.",
                false
            );
        }

        return true;
    }

    // ── Shell helpers ─────────────────────────────────────────────────────────

    /**
     * chmod an toàn — chỉ chấp nhận octal format (ví dụ: '777', '0644').
     */
    public function tryChmod(string $mode, string $filepath): bool
    {
        if (!preg_match('/^[0-7]{3,4}$/', $mode)) {
            return false;
        }
        return $this->tryExec('chmod ' . escapeshellarg($mode) . ' ' . escapeshellarg($filepath));
    }

    /**
     * Chạy shell command qua execute.sh wrapper.
     *
     * @param  string|null $error  Populated with command + output on failure
     * @return bool
     */
    public function tryExec(string $command, ?string &$error = null): bool
    {
        $error      = null;
        $output     = [];
        $returnVar  = 0;
        $cmd        = "/usr/local/bin/execute.sh $command 2>&1";
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            $error = $cmd . "\n" . implode("\n", $output);
            return false;
        }

        return true;
    }

    // ── Directory helpers ─────────────────────────────────────────────────────

    /**
     * Kiểm tra thư mục có rỗng không (không tính . và ..).
     */
    public function isEmptyDir(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = scandir($dir);
        return count($files) <= 2; // chỉ có . và ..
    }
}
