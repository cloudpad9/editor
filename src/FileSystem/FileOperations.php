<?php
namespace CloudPad\FileSystem;

use CloudPad\Core\Output\OutputManager;
use CloudPad\Repository\RepositoryManagerInterface;

/**
 * FileOperations — I/O layer dùng chung cho CloudPad.
 *
 * Phase 10: Loại bỏ Builder → inject RepositoryManagerInterface + OutputManager.
 * Implements FileOperationsInterface.
 */
class FileOperations implements FileOperationsInterface
{
    private RepositoryManagerInterface $repoManager;
    private OutputManager $output;

    public function __construct(RepositoryManagerInterface $repoManager, OutputManager $output)
    {
        $this->repoManager = $repoManager;
        $this->output      = $output;
    }

    // ── Path resolution ───────────────────────────────────────────────────────

    public function getLocalizedPath(string $file, string $repository): string
    {
        if (!empty($repository)) {
            $settings = $this->repoManager->getRepositorySettings($repository);
            $handler  = $settings['handler'] ?? null;
            if (!empty($handler)) {
                $file = $handler->getLocalizedPath($settings, $file);
            }
        }
        return $file;
    }

    public function getRelPath(string $filepath, string $repository): string
    {
        $settings = $this->repoManager->getRepositorySettings($repository);
        $dirs     = $settings['dirs'] ?? [];
        foreach ($dirs as $dir) {
            if (stripos($filepath, $dir) === 0) {
                return substr($filepath, strlen($dir));
            }
        }
        return $filepath;
    }

    // ── Filesystem wrappers ───────────────────────────────────────────────────

    public function fileExists(string $file, string $repository = ''): bool
    {
        return file_exists($this->getLocalizedPath($file, $repository));
    }

    public function rename(string $file, string $newfile, string $repository = ''): bool
    {
        return rename(
            $this->getLocalizedPath($file, $repository),
            $this->getLocalizedPath($newfile, $repository)
        );
    }

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

    public function filePutContents(
        string  $file,
        string  $content,
        string  $repository = '',
        ?string &$message   = null,
        bool    $verbose    = true
    ): bool {
        $message = null;
        $file    = $this->getLocalizedPath($file, $repository);

        if (file_exists($file) && !is_writable($file)) {
            $this->tryChmod('664', $file);
        }

        if (file_exists($file) && !is_writable($file)) {
            $message = "File unwritable: $file<br/>&nbsp;<br/>HINTS:<br/>- chmod 664 $file";
            $this->output->flushLine("[ERROR] $message", true);
            return false;
        }

        $dir = dirname($file);
        if (!empty($dir) && !is_dir($dir)) {
            if (!mkdir($dir, 0775, true)) {
                $message = "Cannot create directory: $dir";
                $this->output->flushLine("[ERROR] $message\n", true);
                return false;
            }
        }

        if (is_dir($dir) && !is_writable($dir)) {
            $this->tryChmod('775', $dir);
        }

        if (!file_put_contents($file, $content)) {
            $message = is_writable($dir)
                ? "Cannot write to file: $file"
                : "Cannot write to directory: $dir";
            $this->output->flushLine("[ERROR] $message\n", true);
            return false;
        }

        if ($verbose) {
            $this->output->flushLine(
                "[NOTICE] File '$file' saved.\n",
                true,
                "File $file saved.",
                false
            );
        }

        return true;
    }

    // ── Shell helpers ─────────────────────────────────────────────────────────

    // Phase 16: max permission cap — không cho phép group/world write
    private const SAFE_MAX_MODE = 0775;

    public function tryChmod(string $mode, string $filepath): bool
    {
        if (!preg_match('/^[0-7]{3,4}$/', $mode)) {
            return false;
        }

        // Phase 16: cap mode ở 0775 — không bao giờ đặt world-write (0777)
        $octal = octdec($mode);
        if ($octal > self::SAFE_MAX_MODE) {
            $octal = self::SAFE_MAX_MODE;
            $mode  = decoct($octal);
        }

        return $this->tryExec('chmod ' . escapeshellarg($mode) . ' ' . escapeshellarg($filepath));
    }

    public function tryExec(string $command, ?string &$error = null): bool
    {
        $error     = null;
        $output    = [];
        $returnVar = 0;
        // Phase 16: wrapper validates + logs all executions
        $cmd       = "/usr/local/bin/execute.sh $command 2>&1";
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            $error = $cmd . "\n" . implode("\n", $output);
            return false;
        }
        return true;
    }

    // ── Directory helpers ─────────────────────────────────────────────────────

    public function isEmptyDir(string $dir): bool
    {
        if (!is_dir($dir)) return false;
        return count(scandir($dir)) <= 2;
    }
}
