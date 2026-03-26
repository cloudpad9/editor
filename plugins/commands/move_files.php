<?php
/**
 * move_files — Backward-compat stub.
 *
 * Phase 5: Logic đã được gộp vào file_transfer.php với operation=move.
 *
 * @deprecated Dùng file_transfer với operation=move thay thế.
 */
function move_files($builder) {
    $_REQUEST['operation'] = 'move';
    $_GET['operation']     = 'move';
    $_POST['operation']    = 'move';

    file_transfer($builder);
}
