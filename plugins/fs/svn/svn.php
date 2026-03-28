<?php
class plugin_fs_svn extends \CloudPad\Plugin\BaseFilesystemPlugin
{
    public function isAccessible(array $settings): bool
    {
        $repodir = $settings['svn']['dir'] ?? '';
        return is_dir($repodir);
    }

    public function init(array &$settings): void
    {
        $path     = $settings['svn']['path'];
        $reponame = basename($path);
        $repodir  = $settings['svn']['dir'] ?? '';

        if (empty($repodir)) {
            $repodir = $this->builder->getUserRepositoryDir() . '/' . $reponame;
        }

        $settings['dirs']     = array_merge($settings['dirs']     ?? [], [$repodir]);
        $settings['excludes'] = array_merge($settings['excludes'] ?? [], [$repodir . '/.svn']);
    }

    public function getRepositoryOperations(array $settings): array
    {
        $path     = $settings['svn']['path'];
        $repodir  = $settings['svn']['dir'] ?? '';
        $reponame = basename($path);

        if (empty($repodir)) {
            $repodir = $this->builder->getUserRepositoryDir() . '/' . $reponame;
        }

        $svn = file_exists('/usr/local/bin/svn') ? '/usr/local/bin/svn' : 'svn';

        $operations = [
            'svn checkout' => "$svn co $path $repodir",
            'svn info'     => "$svn info $repodir",
            'svn status'   => "$svn status $repodir",
            'svn update'   => "$svn up $repodir",
            'svn commit'   => "$svn commit -m 'X' $repodir",
        ];

        return array_merge($operations, parent::getRepositoryOperations($settings));
    }

    public function getLocalizedPath(array $settings, string $path): string
    {
        return $path;
    }
}
