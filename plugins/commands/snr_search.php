<?php
/**
 * Action: snr-search (Search & Replace)
 * Migrated from: inline action trong index.php (Phase 2)
 *
 * TODO Phase 5: Gộp với search_and_replace.php và loại bỏ Builder::snr_search()
 */
function snr_search($builder) {
    $repository         = \CloudPad\Core\Request::require('repository');
    $search             = \CloudPad\Core\Request::getString('search');
    $replace            = \CloudPad\Core\Request::getString('replace');
    $batch_snr          = \CloudPad\Core\Request::getString('batch-snr');
    $case_sensitive     = \CloudPad\Core\Request::getBool('case-sensitive');
    $search_by_filename = \CloudPad\Core\Request::getBool('search-by-filename');
    $force_replace      = \CloudPad\Core\Request::getBool('force-replace');
    $force_delete       = \CloudPad\Core\Request::getBool('force-delete');
    $confirm_delete     = \CloudPad\Core\Request::getBool('confirm-delete');
    $max_files_returned = \CloudPad\Core\Request::getInt('max-files-returned', 20);
    $file_mask          = \CloudPad\Core\Request::getString('file-mask');

    // Lưu state vào session để tab SNR hiển thị lại đúng
    $_SESSION['snr-repository']     = $repository;
    $_SESSION['search']             = $search;
    $_SESSION['file-mask']          = $file_mask;
    $_SESSION['max-files-returned'] = $max_files_returned;
    $_SESSION['replace']            = $replace;
    $_SESSION['batch-snr']          = $batch_snr;
    $_SESSION['snr-batch-mode']     = !empty($batch_snr);

    if (!empty($batch_snr)) {
        // Batch mode: mỗi dòng là một cặp "search => replace"
        $lines     = preg_split("/\n/", $batch_snr, -1, PREG_SPLIT_NO_EMPTY);
        $snr_batch = [];

        foreach ($lines as $line) {
            $parts = explode('=>', $line, 2);
            if (count($parts) < 2) continue;

            $s = trim($parts[0]);
            $r = trim($parts[1]);

            $snr_batch[$s] = $r;
        }

        foreach ($snr_batch as $s => $r) {
            if (strcmp($s, $r) === 0) continue; // Bỏ qua nếu search = replace

            $builder->snr_search(
                $repository, $s, !$case_sensitive, $file_mask,
                $max_files_returned, $search_by_filename,
                $force_replace, $r, $force_delete && $confirm_delete
            );
        }
    } else {
        $builder->snr_search(
            $repository, $search, !$case_sensitive, $file_mask,
            $max_files_returned, $search_by_filename,
            $force_replace, $replace, $force_delete && $confirm_delete
        );
    }
}
