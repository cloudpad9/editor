<?php
namespace CloudPad\Editor;

class RevisionManager
{
    private \CloudPad\Auth\AuthServiceInterface $auth;
    private \CloudPad\FileSystem\FileOperationsInterface $fileOps;
    private \CloudPad\Repository\RepositoryManagerInterface $repoManager;

    public function __construct(
        \CloudPad\Auth\AuthServiceInterface $auth,
        \CloudPad\FileSystem\FileOperationsInterface $fileOps,
        \CloudPad\Repository\RepositoryManagerInterface $repoManager
    ) {
        $this->auth        = $auth;
        $this->fileOps     = $fileOps;
        $this->repoManager = $repoManager;
    }

    // ── Save revisions ────────────────────────────────────────────────────────

    public function saveFileRevision(string $filepath, string $content): void
    {
        $dir      = $this->auth->getUserRevisionDir();
        $prefix   = $this->getRevisionPrefix($filepath);
        $revcount = $this->getRevisionCount($dir, $prefix);
        $revfile  = $dir . '/' . $prefix . '.' . $revcount;
        $newfile  = $dir . '/' . $prefix . '.' . ($revcount + 1);

        if (!file_exists($revfile) || $content != $this->fileOps->fileGetContents($revfile)) {
            $this->fileOps->filePutContents($newfile, $content);
        }
    }

    public function createTempRevision(string $filepath, string $content): void
    {
        $dir      = $this->auth->getUserTempRevisionDir();
        $prefix   = $this->getRevisionPrefix($filepath);
        $revcount = $this->getRevisionCount($dir, $prefix);
        $revfile  = $dir . '/' . $prefix . '.' . $revcount;
        $newfile  = $dir . '/' . $prefix . '.' . ($revcount + 1);

        if (!file_exists($revfile) || $content != $this->fileOps->fileGetContents($revfile)) {
            $this->fileOps->filePutContents($newfile, $content);
        }
    }

    // ── Read revisions ────────────────────────────────────────────────────────

    public function getRevisionCount(string $revdir, string $filename): int
    {
        $filepaths  = glob("$revdir/$filename.*") ?: [];
        $maxcount   = 10;
        $file2suffix = [];

        foreach ($filepaths as $fp) {
            if (preg_match('/\.([0-9]+)$/is', $fp, $match)) {
                $file2suffix[$fp] = (int)$match[1];
            }
        }

        arsort($file2suffix);

        $i         = 0;
        $maxsuffix = 0;

        foreach ($file2suffix as $fp => $suffix) {
            $i++;

            if ($i === 1) {
                $maxsuffix = $suffix;
            }

            if ($i > $maxcount) {
                unlink($fp);
            }
        }

        return $maxsuffix;
    }

    public function getRevisionPrefix(string $filepath): string
    {
        return basename($filepath) . '.' . substr(md5($filepath), 0, 6);
    }

    public function getLatestRevisionContent(string $filepath): ?string
    {
        $dir      = $this->auth->getUserRevisionDir();
        $prefix   = $this->getRevisionPrefix($filepath);
        $revcount = $this->getRevisionCount($dir, $prefix);

        if (!$revcount) {
            return null;
        }

        $revfile = $dir . '/' . $prefix . '.' . $revcount;

        if (!file_exists($revfile)) {
            return null;
        }

        $content = $this->fileOps->fileGetContents($revfile);
        unlink($revfile);

        return $content;
    }

    public function getLatestTempRevisionContent(string $filepath): ?string
    {
        $dir      = $this->auth->getUserTempRevisionDir();
        $prefix   = $this->getRevisionPrefix($filepath);
        $revcount = $this->getRevisionCount($dir, $prefix);

        if (!$revcount) {
            return null;
        }

        $revfile = $dir . '/' . $prefix . '.' . $revcount;

        if (!file_exists($revfile)) {
            return null;
        }

        return $this->fileOps->fileGetContents($revfile);
    }

    // ── File-level actions (delegate response) ────────────────────────────────

    public function revertFile(string $filename, string $repository): void
    {
        $filepath = $this->repoManager->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::fail('Source file not found.');
        }

        $content = $this->getLatestRevisionContent($filepath);

        if (empty($content)) {
            \CloudPad\Core\Response::fail('File revisions not found.');
        }

        $this->fileOps->filePutContents($filepath, $content);

        \CloudPad\Core\Response::ok([
            'content'    => $content,
            'filename'   => $filename,
            'repository' => $repository,
        ]);
    }

    public function recoverFile(string $filename, string $repository): void
    {
        $filepath = $this->repoManager->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::fail('Source file not found.');
        }

        $content = $this->getLatestTempRevisionContent($filepath);

        if (empty($content)) {
            \CloudPad\Core\Response::fail('File revisions not found.');
        }

        $this->fileOps->filePutContents($filepath, $content);

        \CloudPad\Core\Response::ok([
            'content'    => $content,
            'filename'   => $filename,
            'repository' => $repository,
        ]);
    }

    public function reloadFile(string $filename, string $repository): void
    {
        $filepath = $this->repoManager->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::fail('Source file not found.');
        }

        $content = $this->fileOps->fileGetContents($filepath, $repository);

        \CloudPad\Core\Response::ok([
            'content'    => $content,
            'filename'   => $filename,
            'repository' => $repository,
        ]);
    }
}
