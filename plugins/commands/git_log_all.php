<?php
/**
 * git_log_all — Backward-compat stub.
 *
 * Phase 5: Logic đã được gộp vào git_log.php với scope=all.
 *
 * @deprecated Dùng git_log với scope=all thay thế.
 */
function git_log_all($builder) {
    $_REQUEST['scope'] = 'all';
    $_GET['scope']     = 'all';
    $_POST['scope']    = 'all';

    git_log($builder);
}
