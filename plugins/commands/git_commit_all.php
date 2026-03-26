<?php
/**
 * git_commit_all — Backward-compat stub.
 *
 * Phase 5: Logic đã được gộp vào git_commit.php với scope=all.
 * Stub này giữ lại để các JS call cũ (action=git-commit-all) vẫn hoạt động
 * cho đến khi frontend được cập nhật sang action=git-commit&scope=all.
 *
 * @deprecated Dùng git_commit với scope=all thay thế.
 */
function git_commit_all($builder) {
    // Inject scope=all vào request rồi delegate sang git_commit()
    $_REQUEST['scope'] = 'all';
    $_GET['scope']     = 'all';
    $_POST['scope']    = 'all';

    git_commit($builder);
}
