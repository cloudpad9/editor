<?php
namespace CloudPad\Git;

interface GitServiceInterface
{
    public function execGitCommand(string $repoDir, string $subCmd, string &$output = ''): bool;
    public function getGitInfo(string $filepath): array;
}
