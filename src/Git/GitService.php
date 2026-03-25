<?php
namespace CloudPad\Git;

/**
 * GitService — Git operations layer cho CloudPad.
 *
 * Tách từ Builder::execGitCommand() và Builder::get_git_info().
 * Builder delegates sang đây; plugin commands nên inject/gọi qua Builder.
 *
 * Phase 3 (gap fill) — 2 methods:
 *   - execGitCommand()  : chạy git sub-command trong repo dir, trả bool
 *   - getGitInfo()      : trả metadata về git repo chứa filepath
 */
class GitService
{
    /** @var \Builder */
    private $builder;

    public function __construct(\Builder $builder)
    {
        $this->builder = $builder;
    }

    // ── Core git execution ────────────────────────────────────────────────────

    /**
     * Chạy một git sub-command trong repoDir.
     *
     * Ví dụ: execGitCommand('/var/www/project', 'status --short', $output)
     *
     * @param  string $repoDir   Absolute path to repository root
     * @param  string $subCmd    Git sub-command + args (NOT shell-escaped — do it yourself)
     * @param  string $output    Stdout/stderr merged, passed by reference
     * @return bool              true nếu exit code === 0
     */
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

    // ── Repo metadata ─────────────────────────────────────────────────────────

    /**
     * Trả git metadata cho một absolute file/directory path.
     *
     * Return keys:
     *   ok        => bool    — true khi path nằm trong git repo
     *   toplevel  => string  — absolute repo root (no trailing slash)
     *   relpath   => string  — path relative to toplevel
     *   message   => string  — error description khi ok === false
     *
     * @param  string $filepath  Absolute path (file có thể chưa tồn tại).
     * @return array
     */
    public function getGitInfo(string $filepath): array
    {
        $empty = ['ok' => false, 'toplevel' => '', 'relpath' => '', 'message' => ''];

        // Walk up để tìm ancestor đang tồn tại (file có thể đã bị xoá)
        $probe = $filepath;
        while (!file_exists($probe)) {
            $parent = dirname($probe);
            if ($parent === $probe) break;
            $probe  = $parent;
        }

        if (!file_exists($probe)) {
            return $empty + ['message' => 'Path not found: ' . $filepath];
        }

        $dir = is_dir($probe) ? $probe : dirname($probe);

        $lines    = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($dir) . ' && git rev-parse --show-toplevel 2>&1', $lines, $exitCode);

        if ($exitCode !== 0) {
            return $empty + ['message' => implode("\n", $lines)];
        }

        $toplevel = rtrim(str_replace('\\', '/', implode('', $lines)), '/');

        // Tính relpath của original filepath (có thể không tồn tại)
        $absNorm = rtrim(str_replace('\\', '/', $filepath), '/');
        $prefix  = $toplevel . '/';
        $relpath = (strpos($absNorm, $prefix) === 0)
            ? substr($absNorm, strlen($prefix))
            : basename($filepath);

        return [
            'ok'       => true,
            'toplevel' => $toplevel,
            'relpath'  => $relpath,
            'message'  => '',
        ];
    }
}
