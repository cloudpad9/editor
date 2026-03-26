<?php
/**
 * copy_files — Backward-compat stub.
 *
 * Phase 5: Logic đã được gộp vào file_transfer.php với operation=copy.
 *
 * @deprecated Dùng file_transfer với operation=copy thay thế.
 */
function copy_files($builder) {
    $_REQUEST['operation'] = 'copy';
    $_GET['operation']     = 'copy';
    $_POST['operation']    = 'copy';

    file_transfer($builder);
}
