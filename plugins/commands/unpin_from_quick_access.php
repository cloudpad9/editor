<?php
/**
 * unpin_from_quick_access — Bỏ ghim file/folder khỏi Quick Access.
 * Phase 6.3: $builder->json_response() → json_ok()
 */
function unpin_from_quick_access($builder) {
    $path       = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');

    if (isset($_SESSION['quick-access'])) {
        foreach ($_SESSION['quick-access'] as $index => $item) {
            if ($item['repository'] === $repository && $item['path'] === $path) {
                unset($_SESSION['quick-access'][$index]);
                break;
            }
        }
    }

    json_ok();
}
