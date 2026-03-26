<?php
namespace CloudPad\Search;

interface FileSearchServiceInterface
{
    public function searchForFile(string $filename, string $repository): string;
    public function searchForFiles(string $filename, string $repository, int $limit = 0, bool $exact = false): array;
    public function searchForFilesInArray(string $filename, array $filepaths, int $limit = 0, bool $exact = false): array;
    public function rsearch(string $dir, array $excludes = [], array $includes = []): array;
    public function glob(string $dir): array;
    public function isExcludedPath(string $file, array $excludes, array $includes): bool;
}
