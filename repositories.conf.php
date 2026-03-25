<?php
$repositories = array(
   '_' => array(
        'name' => '[editor]',
        'dirs' => [__DIR__],
        'type' => 'local',
        'excludes' => [],
        'sync' => []
    ),
   'www' => array(
        'name' => 'www',
        'dirs' => ['/var/www'],
        'type' => 'local',
        'excludes' => [],
        'sync' => []
    )
);

return $repositories;
