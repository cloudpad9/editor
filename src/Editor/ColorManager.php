<?php
namespace CloudPad\Editor;

class ColorManager
{
    private \CloudPad\Repository\RepositoryManagerInterface $repoManager;
    private \CloudPad\FileSystem\FileOperationsInterface $fileOps;
    private string $appDir;

    public function __construct(
        \CloudPad\Repository\RepositoryManagerInterface $repoManager,
        \CloudPad\FileSystem\FileOperationsInterface $fileOps,
        string $appDir
    ) {
        $this->repoManager = $repoManager;
        $this->fileOps     = $fileOps;
        $this->appDir      = $appDir;
    }

    public function setColor(string $filename, string $repository, string $color): void
    {
        $filepath = $this->repoManager->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::fail('Source file not found.');
        }

        $colorfile = $this->getColorFile();
        $colors    = file_exists($colorfile)
            ? (json_decode($this->fileOps->fileGetContents($colorfile), true) ?: [])
            : [];

        if (!empty($color)) {
            $colors[$filepath] = $color;
        } else {
            unset($colors[$filepath]);
        }

        $this->fileOps->filePutContents($colorfile, json_encode($colors, JSON_UNESCAPED_UNICODE));
        \CloudPad\Core\Response::ok();
    }

    public function getColor(string $filepath): string
    {
        $colorfile = $this->getColorFile();

        if (file_exists($colorfile)) {
            $colors = json_decode($this->fileOps->fileGetContents($colorfile), true) ?: [];
            return $colors[$filepath] ?? '';
        }

        return '';
    }

    public function getColorFile(): string
    {
        $dir = $this->appDir . '/tmp/' . $_SESSION['builder.username'];

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . '/.color';
    }
}
