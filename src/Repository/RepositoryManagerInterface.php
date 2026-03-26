<?php
namespace CloudPad\Repository;

interface RepositoryManagerInterface
{
    public function getRepositories(): array;
    public function getRepositorySettings(string $repository): ?array;
    public function hasRepositoryPermission(string $repository): bool;
    public function getRepositoryFilePaths(string $repository, bool $forceRebuild = false): array;
    public function getProjectFilePaths(string $repository, bool $forceRebuild = false): array;
    public function getRepositoryWisePath(string $filepath, string $repository, string $filename): string;
    public function getFileRepository(string $filepath): string;
    public function getRepositoryCacheFile(string $repository): string;
    public function getAbsolutePath(string $path, string $repository): string;
    public function getAbsoluteFilePath(string $filename, string $repository): string;
}
