<?php
namespace CloudPad\Git;

/**
 * GitService — Git operations layer cho CloudPad.
 *
 * Phase 10: Loại bỏ Builder dependency — không cần inject gì cả.
 * Implements GitServiceInterface.
 */
class GitService implements GitServiceInterface
{
    // Không có dependencies — GitService hoàn toàn standalone.

    public function execGitCommand(string $repoDir, string $subCmd, string &$output = ''): bool
    {
        $output   = '';
        $lines    = [];
        $exitCode = 0;
        $cmd      = 'cd ' . escapeshellarg($repoDir) . ' && git ' . $subCmd . ' 2>&1';
        exec($cmd, $lines, $exitCode);
        $output = implode("\n", $lines);
        return $exitCode === 0;
    }

    public function getGitInfo(string $filepath): array
    {
        $empty = ['ok' => false, 'toplevel' => '', 'relpath' => '', 'message' => ''];

        $probe = $filepath;
        while (!file_exists($probe)) {
            $parent = dirname($probe);
            if ($parent === $probe) break;
            $probe  = $parent;
        }

        if (!file_exists($probe)) {
            return $empty + ['message' => 'Path not found: ' . $filepath];
        }

        $dir      = is_dir($probe) ? $probe : dirname($probe);
        $lines    = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($dir) . ' && git rev-parse --show-toplevel 2>&1', $lines, $exitCode);

        if ($exitCode !== 0) {
            return $empty + ['message' => implode("\n", $lines)];
        }

        $toplevel = rtrim(str_replace('\\', '/', implode('', $lines)), '/');
        $absNorm  = rtrim(str_replace('\\', '/', $filepath), '/');
        $prefix   = $toplevel . '/';
        $relpath  = (strpos($absNorm, $prefix) === 0)
            ? substr($absNorm, strlen($prefix))
            : basename($filepath);

        return ['ok' => true, 'toplevel' => $toplevel, 'relpath' => $relpath, 'message' => ''];
    }
}
