<?php
class plugin_fs_local extends \CloudPad\Plugin\BaseFilesystemPlugin
{
    public function isAccessible(array $settings): bool
    {
        $dirs = $settings['dirs'] ?? [];
        foreach ($dirs as $dir) {
            if (is_dir($dir)) return true;
        }
        return false;
    }

    public function getRepositoryOperations(array $settings): array
    {
        $dirs       = $settings['dirs']  ?? [];
        $repository = $settings['code'] ?? '';
        $operations = [];

        foreach ($dirs as $dir) {
            if (file_exists($dir . '/composer.json')) {
                $operations['composer install'] = "cd $dir; composer install";
                $operations['composer update']  = "cd $dir; composer update";
            }
            if (is_dir($dir . '/.git')) {
                $operations['git status'] = "cd $dir; echo \\# repository $repository $dir; git status";
                $operations['git add']    = "cd $dir; git add --all";
                $operations['git commit'] = "cd $dir; git commit -m 'Commit message'";
                $operations['git rebase'] = "cd $dir; git fetch; git rebase origin/master";
                $operations['git push']   = "cd $dir; git push origin master";
            }
            if (is_dir($dir . '/.svn')) {
                $svn = file_exists('/usr/local/bin/svn') ? '/usr/local/bin/svn' : 'svn';
                $operations['svn info']   = "$svn info $dir";
                $operations['svn status'] = "$svn status $dir";
                $operations['svn update'] = "$svn up $dir";
                $operations['svn commit'] = "$svn commit -m 'X' $dir";
            }
        }
        return $operations;
    }
}
