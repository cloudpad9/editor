<?php
class plugin_fs_git extends \CloudPad\Plugin\BaseFilesystemPlugin
{
    public function isAccessible(array $settings): bool
    {
        $repodir = $settings['git']['dir'] ?? '';
        return !is_object($repodir) && is_dir($repodir);
    }

    public function init(array &$settings): void
    {
        $path     = $settings['git']['path'];
        $reponame = basename($path);
        // FIX: xoá dead code `if (true || empty($repodir))`
        $repodir  = $this->builder->getUserRepositoryDir() . '/' . $reponame;

        $settings['dirs']     = array_merge($settings['dirs']     ?? [], [$repodir]);
        $settings['excludes'] = array_merge($settings['excludes'] ?? [], [$repodir . '/.git']);
    }

    public function getRepositoryOperations(array $settings): array
    {
        $path       = $settings['git']['path'];
        $reponame   = basename($path);
        $repodir    = $this->builder->getUserRepositoryDir() . '/' . $reponame;
        $repository = $settings['code'] ?? '';

        $operations = [
            'git status' => "cd $repodir; echo \\# repository $repository $repodir; git status",
            'git add'    => "cd $repodir; git add --all",
            'git commit' => "cd $repodir; git commit -m 'Commit message'",
            'git rebase' => "cd $repodir; git fetch; git rebase origin/master",
            'git push'   => "cd $repodir; git push origin master",
        ];

        return array_merge($operations, parent::getRepositoryOperations($settings));
    }

    public function getLocalizedPath(array $settings, string $path): string
    {
        return $path;
    }
}
