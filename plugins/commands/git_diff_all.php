<?php
/**
 * git_diff_all — Backward-compat stub.
 *
 * Phase 5: Logic đã được gộp vào git_diff.php với scope=all.
 *
 * @deprecated Dùng git_diff với scope=all thay thế.
 */
function git_diff_all($builder) {
    $_REQUEST['scope'] = 'all';
    $_GET['scope']     = 'all';
    $_POST['scope']    = 'all';

    git_diff($builder);
}
