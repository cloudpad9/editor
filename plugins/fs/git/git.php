<?php class plugin_fs_git extends plugin_fs {
    function isAccessible($settings) {
        $repodir = isset($settings['git']['dir'])? $settings['git']['dir'] : '';

        if (is_object($repodir)) {
            return false;
        }

        if (!is_dir($repodir)) {
            return false;
        }

        return true;
    }

    function init(&$settings) {
        $path = $settings['git']['path'];
        $repodir = isset($settings['git']['dir'])? $settings['git']['dir'] : '';
        $repository = isset($settings['code'])? $settings['code'] : '';

        $reponame = basename($path);

        if (true || empty($repodir)) {
            $repodir = $this->builder->getUserRepositoryDir().'/'.$reponame;
        }

        if (isset($settings['dirs'])) {
            $settings['dirs'][] = $repodir;
        } else {
            $settings['dirs'] = array($repodir);
        }

        if (isset($settings['excludes'])) {
            $settings['excludes'][] = $repodir.'/.git';
        } else {
            $settings['excludes'] = array($repodir.'/.git');
        }

        // if (!is_dir($repodir.'/.git')) {
        //     $cmd = "git clone $path $repodir";

        //     $this->builder->ssh_exec_2($cmd);
        // }
    }

    function getRepositoryOperations($settings) {
        $path = $settings['git']['path'];
        $repodir = isset($settings['git']['dir'])? $settings['git']['dir'] : '';
        $repository = isset($settings['code'])? $settings['code'] : '';

        $reponame = basename($path);

        if (true || empty($repodir)) {
            $repodir = $this->builder->getUserRepositoryDir().'/'.$reponame;
        }

        $operations = array(
            'git status' => "cd $repodir; echo \# repository $repository $repodir; git status",
            'git add' => "cd $repodir; git add --all",
            'git commit' => "cd $repodir; git commit -m 'Commit message'",
            'git rebase' => "cd $repodir; git fetch; git rebase origin/master",
            'git push' => "cd $repodir; git push origin master"
        );

        $operations = array_merge($operations, parent::getRepositoryOperations($settings));

        return $operations;
    }

    function getLocalizedPath($settings, $path) {
        return $path;
    }
}
