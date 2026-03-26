<?php
namespace CloudPad\Search;

use CloudPad\Core\Output\OutputManager;
use CloudPad\Repository\RepositoryManagerInterface;

/**
 * FileSearchService — Tìm kiếm files trong repository.
 *
 * Phase 10: Loại bỏ Builder → inject OutputManager + RepositoryManagerInterface.
 * Implements FileSearchServiceInterface.
 */
class FileSearchService implements FileSearchServiceInterface
{
    private OutputManager $output;
    private ?RepositoryManagerInterface $repoManager = null;

    public function __construct(OutputManager $output)
    {
        $this->output = $output;
    }

    /**
     * Setter injection để phá circular dependency:
     * FileSearchService ↔ RepositoryManager.
     * Gọi từ Container sau khi cả 2 service đã được khởi tạo.
     */
    public function setRepositoryManager(RepositoryManagerInterface $repoManager): void
    {
        $this->repoManager = $repoManager;
    }

    public function searchForFile(string $filename, string $repository): string
    {
        $files = $this->searchForFiles($filename, $repository, 1, true);
        return !empty($files) ? $files[0] : '';
    }

    public function searchForFiles(string $filename, string $repository, int $limit = 0, bool $exact = false): array
    {
        if ($this->repoManager === null) {
            return [];
        }
        $filepaths = $this->repoManager->getRepositoryFilePaths($repository, false);
        return $this->searchForFilesInArray($filename, $filepaths, $limit, $exact);
    }

    public function searchForFilesInArray(string $filename, array $filepaths, int $limit = 0, bool $exact = false): array
    {
        $nameonly      = stripos($filename, '/') === false;
        $withregex     = stripos($filename, '*') !== false;
        $results       = [];
        $count         = 0;
        $filenameRegex = '';

        if ($withregex) {
            $filenameRegex = '/' .
                str_replace(['\\*', '\\/', '\\.', '\\-'], ['.*', '\\/', '\\.', '\\-'],
                    preg_quote($filename, '/')) . '/is';
        }

        foreach ($filepaths as $filepath) {
            $compare = $nameonly ? basename($filepath) : $filepath;

            if ($withregex) {
                if (!preg_match($filenameRegex, $compare)) continue;
            } elseif ($exact) {
                if (strcasecmp($compare, $filename) !== 0) continue;
            } else {
                if (stripos($compare, $filename) === false) continue;
            }

            $results[] = $filepath;
            $count++;
            if ($limit > 0 && $count >= $limit) break;
        }

        return $results;
    }

    public function rsearch(string $dir, array $excludes = [], array $includes = []): array
    {
        if (!is_dir($dir)) {
            $this->output->verbose('[ERROR] Directory does not exist <-- ' . $dir);
            return [];
        }

        $results = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $file) {
            if ($file->isDir()) continue;
            $path = $file->getPathname();
            if (!$this->isExcludedPath($path, $excludes, $includes)) {
                $results[] = $path;
            }
        }

        return $results;
    }

    public function glob(string $dir): array
    {
        if (!is_dir($dir)) return [];
        $entries = [];
        foreach (new \DirectoryIterator($dir) as $item) {
            if (!$item->isDot()) $entries[] = $item->getPathname();
        }
        return $entries;
    }

    public function isExcludedPath(string $file, array $excludes, array $includes): bool
    {
        foreach ($includes as $include) {
            if (stripos($file, $include) !== false) return false;
        }
        foreach ($excludes as $exclude) {
            if (stripos($file, $exclude) !== false) return true;
        }
        return false;
    }
}
