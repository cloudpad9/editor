<?php
// IMPORTANT: SFTP resources should not be closed before accessing files.
// So, keep them global so that they can last after function exits.
// Otherwise, a "502 Bad Gateway" error may happen
global $sftps;

class plugin_fs_sftp extends plugin_fs {
    function init(&$settings) {
        return;
    }

    function getRepositoryOperations($settings) {
        return parent::getRepositoryOperations($settings);
    }

    function getLocalizedPath($settings, $file) {
        set_time_limit(0);

        $sftp = $this->get_sftp_connection($settings['sftp']['host'], $settings['sftp']['port'], $settings['sftp']['username'], $settings['sftp']['password']);

        $fs_prefix = 'ssh2.sftp://'.intval($sftp);

        if (empty($fs_prefix)) {
            die("[ERROR] Cannot connect to remote file system via SFTP\n");
        }

        $file = $fs_prefix.$file;

        return $file;
    }

    function get_sftp_connection($host, $port, $username, $password) {
        global $sftps;

        $key = "$host, $port, $username, $password";

        if (!isset($sftps[$key])) {
            $connection = ssh2_connect($host, $port);

            if (!$connection) {
                $this->verbose('[ERROR] Cannot connect to the SFTP server --> '.$host.':'.$port);
                return;
            }

            // Authentication using a public key
            $authenticated = ssh2_auth_password ($connection, $username, $password);

            if (!$authenticated) {
                $this->verbose('[ERROR] Cannot authenticate with the SFTP server using username/password');
                return;
            }

            // Initialize SFTP subsystem
            $sftp = ssh2_sftp($connection);

            $sftps[$key] = $sftp;
        }

        return $sftps[$key];
    }
}
