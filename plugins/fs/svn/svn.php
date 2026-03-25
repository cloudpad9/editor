<?php class plugin_fs_svn extends plugin_fs {
    function isAccessible($settings) {
        $repodir = isset($settings['svn']['dir'])? $settings['svn']['dir'] : '';

        if (!is_dir($repodir)) {
            return false;
        }

        return true;
    }

    function init(&$settings) {
        $path = $settings['svn']['path'];
        $repodir = isset($settings['svn']['dir'])? $settings['svn']['dir'] : '';

        $reponame = basename($path);

        if (empty($repodir)) {
            $repodir = $this->builder->getUserRepositoryDir().'/'.$reponame;
        }

        if (isset($settings['dirs'])) {
            $settings['dirs'][] = $repodir;
        } else {
            $settings['dirs'] = array($repodir);
        }

        if (isset($settings['excludes'])) {
            $settings['excludes'][] = $repodir.'/.svn';
        } else {
            $settings['excludes'] = array($repodir.'/.svn');
        }

        // if (!is_dir($repodir)) {
        //     $cmd = "svn co $path $repodir";

        //     $this->builder->ssh_exec_2($cmd);
        // }
    }

    function getRepositoryOperations($settings) {
        $path = $settings['svn']['path'];
        $repodir = isset($settings['svn']['dir'])? $settings['svn']['dir'] : '';

        $reponame = basename($path);

        if (empty($repodir)) {
            $repodir = $this->builder->getUserRepositoryDir().'/'.$reponame;
        }

        $svn = file_exists('/usr/local/bin/svn')? '/usr/local/bin/svn' : 'svn';

        $operations = array(
            'svn checkout' => "$svn co $path $repodir",
            'svn info' => "$svn info $repodir",
            'svn status' => "$svn status $repodir",
            'svn update' => "$svn up $repodir",
            'svn commit' => "$svn commit -m 'X' $repodir"
        );

        $operations = array_merge($operations, parent::getRepositoryOperations($settings));

        return $operations;
    }

    function getLocalizedPath($settings, $path) {
        return $path;
    }
}
