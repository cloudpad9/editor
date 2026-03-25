<?php
namespace CloudPad\Repository;

class RepositoryManager
{
    private \Builder $builder;
    private string $appDir;

    public function __construct(\Builder $builder, string $appDir)
    {
        $this->builder = $builder;
        $this->appDir  = $appDir;
    }

    // ── Repository list ───────────────────────────────────────────────────────

    public function getRepositoriesFromFile(): array
    {
        return include($this->appDir . '/repositories.conf.php');
    }

    public function getRepositories(): array
    {
        static $repositories = null;

        if ($repositories === null) {
            $all  = $this->getRepositoriesFromFile();
            $repos = $_SESSION['builder.user']['repositories'];
            $repositories = [];

            foreach ($repos as $repo) {
                if (!isset($all[$repo])) {
                    continue;
                }

                $settings = $all[$repo];
                $handler  = $this->getRepositoryHandler($settings);

                if (empty($handler)) {
                    $this->builder->error("Cannot get handler of repository '" . $settings['name'] . "'");
                    continue;
                }

                if (!$handler->isAccessible($settings)) {
                    continue;
                }

                $handler->init($settings);
                $settings['handler'] = $handler;

                $realrepo = $settings['code'] ?? $repo;
                $repositories[$realrepo] = $settings;
            }
        }

        return $repositories;
    }

    public function getRepositorySettings(string $repository): ?array
    {
        static $cache = [];

        if ($repository === '*') {
            return [];
        }

        if (isset($cache[$repository])) {
            return $cache[$repository];
        }

        $repositories = $this->getRepositories();

        if (!isset($repositories[$repository])) {
            $this->builder->verbose("[ERROR] Repository '$repository' is not found");
            return null;
        }

        $cache[$repository] = $repositories[$repository];

        return $cache[$repository];
    }

    public function hasRepositoryPermission(string $repository): bool
    {
        if (empty($repository)) {
            return true;
        }

        $repositories = $this->getRepositories();

        return isset($repositories[$repository]);
    }

    // ── Repository handlers ───────────────────────────────────────────────────

    public function getRepositoryHandler(array $settings): ?object
    {
        $fs = $settings['type'] ?? 'local';

        if ($this->builder->has_plugin_fs($fs, $handler)) {
            return $handler;
        }

        return null;
    }

    // ── Path resolution ───────────────────────────────────────────────────────

    /**
     * Resolve `<branch>://<relpath>` or plain path against repository dirs.
     */
    public function getAbsolutePath(string $path, string $repository): string
    {
        $settings = $this->getRepositorySettings($repository);
        $dirs     = $settings['dirs'];
        $branch   = '';

        if (preg_match('/^([0-9]+):\/\/(.*)/', trim($path), $match)) {
            $branch = $match[1];
            $path   = $match[2];
        }

        $path = ltrim($path, '/');

        if (!empty($branch)) {
            $branchDir = $dirs[$branch - 1] ?? '';

            if (!empty($branchDir) && is_dir($branchDir)) {
                return rtrim($branchDir, '/') . '/' . $path;
            }
        } else {
            foreach ($dirs as $dir) {
                $dir = rtrim($dir, '/');

                if (file_exists($dir . '/' . $path)) {
                    return $dir . '/' . $path;
                }
            }
        }

        return $path;
    }

    public function getAbsoluteFilePath(string $filename, string $repository): string
    {
        $filepath    = $_SESSION['filepaths'][$repository][$filename] ?? '';
        $isTempFile  = ($filename[0] === '*');

        if (!empty($filepath) && !$isTempFile && basename($filename) !== basename($filepath)) {
            $filepath = null;
        }

        $branch = '';

        if (preg_match('/^([0-9]+):\/\/(.*)/', trim($filename), $match)) {
            $branch   = $match[1];
            $filename = $match[2];
        }

        if (!empty($branch)) {
            $settings  = $this->getRepositorySettings($repository);
            $dirs      = $settings['dirs'] ?? [];
            $branchDir = $dirs[$branch - 1] ?? '';

            if (!empty($branchDir) && is_dir($branchDir)) {
                return rtrim($branchDir, '/') . '/' . $filename;
            }
        }

        if (empty($filepath)) {
            $filepath = $this->builder->searchForFile($filename, $repository);
        }

        if (empty($filepath)) {
            $settings = $this->getRepositorySettings($repository);
            $dirs     = $settings['dirs'] ?? [];

            foreach ($dirs as $dir) {
                if (file_exists($dir . '/' . $filename)) {
                    return $dir . '/' . $filename;
                }
            }
        }

        return (string)$filepath;
    }

    public function getRepositoryWisePath(string $filepath, string $repository, string $filename): string
    {
        if (empty($repository)) {
            return $filename;
        }

        $settings = $this->getRepositorySettings($repository);
        $dirs     = $settings['dirs'] ?? [];

        foreach ($dirs as $index => $dir) {
            if (stripos($filepath, $dir) !== 0) {
                continue;
            }

            $filepath = str_replace(rtrim($dir, '/') . '/', ($index + 1) . '://', $filepath);
            break;
        }

        return $filepath;
    }

    public function getFileRepository(string $filepath): string
    {
        $repositories = $this->getRepositories();

        foreach ($repositories as $repository => $settings) {
            foreach ($settings['dirs'] as $dir) {
                if (stripos($filepath, $dir) !== 0) {
                    continue;
                }

                $filepaths = $this->getRepositoryFilePaths($repository, false);

                if (in_array($filepath, $filepaths)) {
                    return $repository;
                }
            }
        }

        return '';
    }

    // ── File index / cache ────────────────────────────────────────────────────

    public function getRepositoryCacheFile(string $repository): string
    {
        $settings  = $this->getRepositorySettings($repository);
        $dirs      = $settings['dirs'];
        $signature = md5($repository . implode(',', $dirs));

        return $this->appDir . '/cache/' . $signature;
    }

    public function getRepositoryFilePaths(string $repository, bool $forceRebuild = false): array
    {
        if (!$this->hasRepositoryPermission($repository)) {
            return [];
        }

        $settings = $this->getRepositorySettings($repository);
        $dirs     = $settings['dirs'];
        $cachefile = $this->appDir . '/cache/' . md5($repository . implode(',', $dirs));

        if (!file_exists($cachefile) || $forceRebuild) {
            $this->rebuildIndexesUsingRust($dirs, $cachefile);
        }

        return include $cachefile;
    }

    public function getProjectFilePaths(string $repository, bool $forceRebuild = false): array
    {
        if (!$this->hasRepositoryPermission($repository)) {
            return [];
        }

        $settings  = $this->getRepositorySettings($repository);
        $dirs      = $settings['dirs'];
        $excludes  = $settings['excludes'] ?? [];
        $includes  = $settings['includes'] ?? [];

        $filepaths = [];

        foreach ($dirs as $dir) {
            $paths     = $this->builder->rsearch($dir, $excludes, $includes);
            $filepaths = array_merge($filepaths, $paths);
        }

        return $filepaths;
    }

    // ── SFTP ──────────────────────────────────────────────────────────────────

    public function getSftpPrefix(
        string $host,
        int    $port,
        string $username,
        string $password,
        mixed  &$sftp
    ): string {
        $connection = ssh2_connect($host, $port);

        if (!$connection) {
            $this->builder->verbose("[ERROR] Cannot connect to the SFTP server --> $host:$port");
            return '';
        }

        if (!ssh2_auth_password($connection, $username, $password)) {
            $this->builder->verbose('[ERROR] Cannot authenticate with the SFTP server using username/password');
            return '';
        }

        $sftp    = ssh2_sftp($connection);
        $sftp_fd = intval($sftp);

        return 'ssh2.sftp://' . $sftp_fd;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    public function rebuildIndexesUsingRust(array $dirs, string $outputFile): void
    {
        $rustBinary = $this->appDir . '/bin/rust/rebuild-indexes/target/release/rebuild-indexes';

        if (!is_file($rustBinary)) {
            $this->builder->error('Rebuild binary not found. Please build the Rust binary first.');
        }

        $escapedDirs = array_map('escapeshellarg', $dirs);
        $command     = escapeshellarg($rustBinary) . ' ' . implode(' ', $escapedDirs) . ' ' . escapeshellarg($outputFile);

        $this->builder->try_exec($command, $error);

        if (!empty($error)) {
            $this->builder->error($error);
        }
    }
}
