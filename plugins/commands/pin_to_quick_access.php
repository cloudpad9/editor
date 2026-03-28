<?php
/**
 * pin_to_quick_access — Ghim file/folder vào Quick Access.
 * Phase 6.3: \CloudPad\Core\Response::json() → json_ok()
 */
function pin_to_quick_access($builder) {
    $name       = \CloudPad\Core\Request::getString('name');
    $path       = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');

    if (!isset($_SESSION['quick-access'])) {
        $_SESSION['quick-access'] = [];
    }

    $_SESSION['quick-access'][] = [
        'name'       => "[$repository] $name",
        'path'       => $path,
        'repository' => $repository,
    ];

    json_ok();
}
