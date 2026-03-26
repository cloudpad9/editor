<?php
namespace CloudPad\FileSystem;

interface FileOperationsInterface
{
    public function getLocalizedPath(string $file, string $repository): string;
    public function getRelPath(string $filepath, string $repository): string;
    public function fileExists(string $file, string $repository = ''): bool;
    public function rename(string $file, string $newfile, string $repository = ''): bool;
    public function fileGetContents(string $file, string $repository = ''): string;
    public function filePutContents(string $file, string $content, string $repository = '', ?string &$message = null, bool $verbose = true): bool;
    public function tryChmod(string $mode, string $filepath): bool;
    public function tryExec(string $command, ?string &$error = null): bool;
    public function isEmptyDir(string $dir): bool;
}
