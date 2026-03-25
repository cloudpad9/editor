<?php
namespace CloudPad\Search;

class FileSearchService
{
    private \Builder $builder;

    public function __construct(\Builder $builder)
    {
        $this->builder = $builder;
    }

    public function searchForFile(string $filename, string $repository): string
    {
        $files = $this->searchForFiles($filename, $repository, 1, true);
        return !empty($files) ? $files[0] : '';
    }

    public function searchForFiles(string $filename, string $repository, int $limit = 0, bool $exact = false): array
    {
        $filepaths = $this->builder->getRepositoryFilePaths($repository, false);
        return $this->searchForFilesInArray($filename, $filepaths, $limit, $exact);
    }

    public function searchForFilesInArray(string $filename, array $filepaths, int $limit = 0, bool $exact = false): array
    {
        $nameonly    = stripos($filename, '/') === false;
        $withregex   = stripos($filename, '*') !== false;
        $substrLen   = strlen($filename);
        $results     = [];
        $count       = 0;
        $filenameRegex = '';

        if ($withregex) {
            $filenameRegex = '/' .
                str_replace(['\\*', '\\/', '\\.', '\\-'], ['.*', '\\/', '\\.', '\\-'],
                    preg_quote($filename, '/')
                ) . '/is';
        }

        foreach ($filepaths as $path) {
            $matched = (!$exact && stripos($path, $filename) !== false)
                || ($exact && substr_compare($path, $filename, -$substrLen) === 0)
                || ($withregex && preg_match($filenameRegex, $path));

            if ($matched) {
                $results[] = $path;
                $count++;

                if ($limit && $count >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    public function isPathMatched(string $path, string $pattern): bool
    {
        if (strpos($pattern, '*') === false) {
            return stripos($path, $pattern) !== false;
        }

        $pattern = str_replace(['/', '.', '*'], ['\\/', '\\.', '.*'], $pattern);

        return (bool)preg_match('/' . $pattern . '/i', $path);
    }

    public function isExcludedPath(string $file, array $excludes, array $includes): bool
    {
        foreach ($includes as $include) {
            if ($this->isPathMatched($file, $include)) {
                return false;
            }
        }

        foreach ($excludes as $exclude) {
            if ($this->isPathMatched($file, $exclude)) {
                return true;
            }
        }

        return false;
    }

    public function glob(string $dir): array
    {
        $tree = [];

        if (is_dir($dir)) {
            $iterator = new \DirectoryIterator($dir);

            foreach ($iterator as $entry) {
                if ($entry->isFile() || ($entry->isDir() && !$entry->isDot() && $entry->getFilename() !== '.svn')) {
                    $tree[] = $entry->getPathname();
                }
            }
        } else {
            $this->builder->verbose('[ERROR] Directory does not exist <-- ' . $dir);
        }

        return $tree;
    }

    public function rsearch(string $dir, array $excludes = [], array $includes = [], string $fsPrefix = ''): array
    {
        $dirs      = [rtrim($dir, '/')];
        $filepaths = [];

        while ($dirs) {
            $dir  = array_pop($dirs);
            $tree = $this->glob($dir);

            foreach ($tree as $file) {
                if ($this->isExcludedPath($file, $excludes, $includes)) {
                    continue;
                }

                if (is_dir($file) && basename($file) !== 'cache') {
                    $dirs[] = $file;
                } elseif (is_file($file)) {
                    $filepaths[] = $fsPrefix ? str_replace($fsPrefix, '', $file) : $file;
                }
            }
        }

        return $filepaths;
    }
}
