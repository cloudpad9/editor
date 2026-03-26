<?php
namespace CloudPad\Editor;

interface EditorServiceInterface
{
    public function openFileByName(string $filename, bool $fromcache, string $repository): void;
    public function getDirectoryChildren(string $repository, string $path): void;
    public function getDirectoryStructure(string $repository): void;
    public function buildDirectoryStructure(array $filepaths): array;
    public function addToDirectoryStructure(array &$structure, array $parts, string $basePath): void;
    public function fileLiveSearch(string $repository, string $filename): void;
    public function getFileContent(string $filename, string $repository = ''): void;
    public function closeFile(string $filename, string $repository, bool $standalone = false): void;
    public function getUserUploadFiles(): array;
    public function uploadFile(): void;
    public function downloadUserFile(string $filename): void;
    public function deleteUserFile(string $filename): void;
    public function getUserTempFilePaths(): array;
    public function getNewFilePath(): string;
    public function newTempFile(): void;
    public function cloneFile(string $filename, string $repository, string $newname): void;
    public function rebuildSubIndexes(string $filename, string $repository): void;
    public function saveCurrentFile(string $filename, string $repository, string $content, bool $createTempRevisionOnly): void;
    public function setFilePath(string $filename, string $filepath, string $repository): void;
    public function addToRepositoryFilePaths(string $filepath, string $repository): void;
    public function getOpenFiles(bool $tempOnly = false): array;
    public function getEditorOpenFiles(bool $tempOnly = false): array;
}
