<?php
namespace CloudPad\Editor;

class RevisionManager
{
    private \Builder $builder;

    public function __construct(\Builder $builder)
    {
        $this->builder = $builder;
    }

    // ── Save revisions ────────────────────────────────────────────────────────

    public function saveFileRevision(string $filepath, string $content): void
    {
        $dir      = $this->builder->getUserRevisionDir();
        $prefix   = $this->getRevisionPrefix($filepath);
        $revcount = $this->getRevisionCount($dir, $prefix);
        $revfile  = $dir . '/' . $prefix . '.' . $revcount;
        $newfile  = $dir . '/' . $prefix . '.' . ($revcount + 1);

        if (!file_exists($revfile) || $content != $this->builder->file_get_contents($revfile)) {
            $this->builder->file_put_contents($newfile, $content);
        }
    }

    public function createTempRevision(string $filepath, string $content): void
    {
        $dir      = $this->builder->getUserTempRevisionDir();
        $prefix   = $this->getRevisionPrefix($filepath);
        $revcount = $this->getRevisionCount($dir, $prefix);
        $revfile  = $dir . '/' . $prefix . '.' . $revcount;
        $newfile  = $dir . '/' . $prefix . '.' . ($revcount + 1);

        if (!file_exists($revfile) || $content != $this->builder->file_get_contents($revfile)) {
            $this->builder->file_put_contents($newfile, $content);
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
        $dir      = $this->builder->getUserRevisionDir();
        $prefix   = $this->getRevisionPrefix($filepath);
        $revcount = $this->getRevisionCount($dir, $prefix);

        if (!$revcount) {
            return null;
        }

        $revfile = $dir . '/' . $prefix . '.' . $revcount;

        if (!file_exists($revfile)) {
            return null;
        }

        $content = $this->builder->file_get_contents($revfile);
        unlink($revfile);

        return $content;
    }

    public function getLatestTempRevisionContent(string $filepath): ?string
    {
        $dir      = $this->builder->getUserTempRevisionDir();
        $prefix   = $this->getRevisionPrefix($filepath);
        $revcount = $this->getRevisionCount($dir, $prefix);

        if (!$revcount) {
            return null;
        }

        $revfile = $dir . '/' . $prefix . '.' . $revcount;

        if (!file_exists($revfile)) {
            return null;
        }

        return $this->builder->file_get_contents($revfile);
    }

    // ── File-level actions (delegate response) ────────────────────────────────

    public function revertFile(string $filename, string $repository): void
    {
        $filepath = $this->builder->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::fail('Source file not found.');
        }

        $content = $this->getLatestRevisionContent($filepath);

        if (empty($content)) {
            \CloudPad\Core\Response::fail('File revisions not found.');
        }

        $this->builder->file_put_contents($filepath, $content);

        \CloudPad\Core\Response::ok([
            'content'    => $content,
            'filename'   => $filename,
            'repository' => $repository,
        ]);
    }

    public function recoverFile(string $filename, string $repository): void
    {
        $filepath = $this->builder->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::fail('Source file not found.');
        }

        $content = $this->getLatestTempRevisionContent($filepath);

        if (empty($content)) {
            \CloudPad\Core\Response::fail('File revisions not found.');
        }

        $this->builder->file_put_contents($filepath, $content);

        \CloudPad\Core\Response::ok([
            'content'    => $content,
            'filename'   => $filename,
            'repository' => $repository,
        ]);
    }

    public function reloadFile(string $filename, string $repository): void
    {
        $filepath = $this->builder->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::fail('Source file not found.');
        }

        $content = $this->builder->file_get_contents($filepath, $repository);

        \CloudPad\Core\Response::ok([
            'content'    => $content,
            'filename'   => $filename,
            'repository' => $repository,
        ]);
    }
}
