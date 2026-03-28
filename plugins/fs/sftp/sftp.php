<?php
// IMPORTANT: SFTP resources should not be closed before accessing files.
// They are now kept in a static property instead of a global variable.
class plugin_fs_sftp extends \CloudPad\Plugin\BaseFilesystemPlugin
{
    // FIX: xoá `global $sftps` → static property
    private static array $connections = [];

    public function init(array &$settings): void
    {
        // no-op
    }

    public function getRepositoryOperations(array $settings): array
    {
        return parent::getRepositoryOperations($settings);
    }

    public function getLocalizedPath(array $settings, string $file): string
    {
        set_time_limit(0);

        $sftp = $this->getSftpConnection(
            $settings['sftp']['host'],
            (int) $settings['sftp']['port'],
            $settings['sftp']['username'],
            $settings['sftp']['password']
        );

        $fs_prefix = 'ssh2.sftp://' . intval($sftp);

        if (empty($fs_prefix)) {
            // FIX: xoá die() → throw exception
            throw new \CloudPad\Core\Exceptions\FileSystemException(
                'Cannot connect to remote file system via SFTP'
            );
        }

        return $fs_prefix . $file;
    }

    private function getSftpConnection(string $host, int $port, string $username, string $password)
    {
        $key = "$host:$port:$username";

        if (!isset(self::$connections[$key])) {
            $connection = ssh2_connect($host, $port);

            if (!$connection) {
                throw new \CloudPad\Core\Exceptions\FileSystemException(
                    "Cannot connect to SFTP server: $host:$port"
                );
            }

            if (!ssh2_auth_password($connection, $username, $password)) {
                throw new \CloudPad\Core\Exceptions\FileSystemException(
                    'Cannot authenticate with SFTP server'
                );
            }

            self::$connections[$key] = ssh2_sftp($connection);
        }

        return self::$connections[$key];
    }
}
