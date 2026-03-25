<?php
function unpin_from_quick_access($builder) {
    $name = \CloudPad\Core\Request::getString('name');
    $path = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');

    if (isset($_SESSION['quick-access'])) {
        foreach ($_SESSION['quick-access'] as $index => $item) {
            if ($item['repository'] == $repository && $item['path'] == $path) {
                unset($_SESSION['quick-access'][$index]);

                break;
            }
        }
    }

    $builder->json_response(array('success' => true));
}
