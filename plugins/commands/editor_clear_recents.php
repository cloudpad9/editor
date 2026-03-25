<?php
/**
 * Action: editor-clear-recents
 * Migrated from: inline action trong index.php (Phase 2)
 */
function editor_clear_recents($builder) {
    $_SESSION['recentfilepaths'] = [];
    \CloudPad\Core\Response::ok();
}
