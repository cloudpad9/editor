<?php
/**
 * Action: set-color
 * Migrated from: inline action trong index.php (Phase 2)
 */
function set_color($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');
    $color      = \CloudPad\Core\Request::getString('color');

    $builder->get(\CloudPad\Editor\ColorManager::class)->setColor($filename, $repository, $color);
}
