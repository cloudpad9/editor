<?php
$repositories = array(
   '_' => array(
        'name' => '[editor]',
        'dirs' => [__DIR__],
        'type' => 'local',
        'excludes' => [],
        'sync' => []
    ),
   'cloudpad9' => array(
        'name' => 'cloudpad9',
        'dirs' => ['/var/www/cloudpad9'],
        'type' => 'local',
        'excludes' => [],
        'sync' => []
    ),
   'working/builder' => array(
        'name' => 'builder',
        'dirs' => ['/home/debian/working/builder'],
        'type' => 'local',
        'excludes' => [],
        'sync' => []
    ),
   'working/setup' => array(
        'name' => 'working',
        'dirs' => ['/home/debian/working/setup'],
        'type' => 'local',
        'excludes' => [],
        'sync' => []
    ),
    'sftp::demo' => array(
        'name' => 'sftp::demo',
        'type' => 'sftp',
        'sftp' => ['host' => '', 'port' => 22, 'username' => '', 'password' => ''],
        'dirs' => ['/home/demo']
    ),
    'mrpd' => array(
        'name' => '/var/www/mrpd',
        'dirs' => ['/var/www/mrpd'],
        'type' => 'local',
        'excludes' => [],
        'sync' => []
    ),
    'var' => array(
        'name' => '/var/www',
        'dirs' => ['/var/www'],
        'type' => 'local',
        'excludes' => [],
        'sync' => []
    ),
    'srv' => array(
        'name' => '/srv',
        'dirs' => ['/srv'],
        'type' => 'local',
        'excludes' => [],
        'sync' => []
    ),
    'git::demo' => array(
        'name' => 'sftp::demo',
        'type' => 'git',
        'git' => ['path' => 'https://github.com/company/demo']
    )
);

return $repositories;
