<?php
function pin_to_quick_access($builder) {
    $name = \CloudPad\Core\Request::getString('name');
    $path = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');

    if (!isset($_SESSION['quick-access'])) {
        $_SESSION['quick-access'] = [];
    }

    $_SESSION['quick-access'][] = ['name' => "[$repository] $name", 'path' => $path, 'repository' => $repository];

    $builder->json_response(array('success' => true));
}
