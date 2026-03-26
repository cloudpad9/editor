<?php
namespace CloudPad\Plugin;

/**
 * BaseFilesystemPlugin — Base class cho tất cả filesystem plugins.
 *
 * Tách từ class `plugin_fs` trong index.php (Phase 8.1).
 * Các concrete implementations: plugin_fs_local, plugin_fs_git, plugin_fs_sftp, plugin_fs_svn.
 *
 * Backward compat: class toàn cục `plugin_fs` trong index.php được giữ lại
 * dưới dạng alias extend class này cho đến khi plugin files được cập nhật.
 */
class BaseFilesystemPlugin
{
    /** @var \Builder */
    protected $builder;

    public function __construct($builder)
    {
        $this->builder = $builder;
    }

    /**
     * Kiểm tra repository có accessible không.
     * Override trong subclass để thêm custom logic.
     */
    public function isAccessible(array $settings): bool
    {
        return true;
    }

    /**
     * Khởi tạo repository settings.
     * Override trong subclass nếu cần pre-processing.
     */
    public function init(array &$settings): void
    {
        // Default: no-op
    }

    /**
     * Trả danh sách shell commands thường dùng cho repository này.
     * Frontend dùng để populate dropdown "Quick Commands".
     */
    public function getRepositoryOperations(array $settings): array
    {
        $type       = $settings['type']       ?? '';
        $dirs       = $settings['dirs']       ?? [];
        $repository = $settings['code']       ?? '';

        if ($type === 'git') {
            $repodir = $settings['git']['dir'] ?? '';
        } elseif ($type === 'svn') {
            $repodir = $settings['svn']['dir'] ?? '';
        } else {
            $repodir = !empty($dirs) ? $dirs[0] : '';
        }

        $operations = [];

        if (!empty($repodir) && !is_object($repodir) && is_dir($repodir)) {
            if (file_exists($repodir . '/composer.json')) {
                $operations['composer install'] = "cd $repodir; composer install";
                $operations['composer update']  = "cd $repodir; composer update";
            }

            if ($type !== 'git' && is_dir($repodir . '/.git')) {
                $operations['git status'] = "cd $repodir; echo \\# repository $repository $repodir; git status";
                $operations['git pull']   = "cd $repodir; git pull origin master";
                $operations['git commit'] = "cd $repodir; git commit -a -m 'Commit message'";
                $operations['git push']   = "cd $repodir; git push origin master";
            }

            if ($type !== 'svn' && is_dir($repodir . '/.svn')) {
                $operations['svn info']   = "svn info $repodir";
                $operations['svn status'] = "svn status $repodir";
                $operations['svn update'] = "svn up $repodir";
                $operations['svn commit'] = "svn commit -m 'X' $repodir";
            }
        }

        return $operations;
    }

    /**
     * Chuyển đổi path sang dạng local path (dùng cho SFTP/remote fs).
     * Default: trả nguyên path (local filesystem).
     */
    public function getLocalizedPath(array $settings, string $path): string
    {
        return $path;
    }
}
