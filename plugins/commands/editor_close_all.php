<?php
/**
 * Action: editor-close-all
 * Migrated from: inline action trong index.php (Phase 2)
 */
function editor_close_all($builder) {
    $_SESSION['openfilepaths'] = [];
    \CloudPad\Core\Response::ok();
}
