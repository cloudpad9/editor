<?php
/**
 * Action: upload-file-x
 * Gộp với upload_file — cả hai action đều gọi Builder::upload_file().
 * Migrated from: inline action trong index.php (Phase 2)
 */
function upload_file_x($builder) {
    $builder->getEditorService()->uploadFile();
}
