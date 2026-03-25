<?php
namespace CloudPad\Editor;

class SyncService
{
    private \Builder $builder;

    public function __construct(\Builder $builder)
    {
        $this->builder = $builder;
    }

    public function syncFile(string $filename, string $repository, bool $revert = false): void
    {
        $filepath = $this->builder->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath) || !file_exists($filepath)) {
            \CloudPad\Core\Response::fail('Source file not found.');
        }

        $syncDest = $this->getSyncDest($filepath);

        if (!empty($syncDest)) {
            $dir = dirname($syncDest);

            if (!empty($dir) && !is_dir($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    \CloudPad\Core\Response::fail("[ERROR] Cannot create directory: $dir");
                }
            }

            if ($revert) {
                [$filepath, $syncDest] = [$syncDest, $filepath];
            }

            $content = $this->builder->file_get_contents($filepath, $repository);

            if ($this->builder->file_put_contents($syncDest, $content, '', $message)) {
                $message = 'File synced.';
            } else {
                $message = "Sync failed. $message";
            }
        } else {
            $message = 'Sync is not enabled for this file.';
        }

        \CloudPad\Core\Response::ok(['message' => $message]);
    }

    public function getSyncDest(string $filepath): string
    {
        $repository  = $this->builder->getFileRepository($filepath);
        $sync_routes = [];

        if (!empty($repository)) {
            $settings = $this->builder->getRepositorySettings($repository);

            if (isset($settings['sync']) && !empty($settings['sync'])) {
                $sync_routes = $settings['sync'];
            }
        }

        foreach ($sync_routes as $s => $d) {
            if (stripos($filepath, $s) === 0) {
                return str_replace($s, $d, $filepath);
            }
        }

        return '';
    }
}
