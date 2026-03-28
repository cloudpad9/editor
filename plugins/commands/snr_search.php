<?php
use CloudPad\Core\Request;
use CloudPad\Search\SearchAndReplace\SNRService;
use CloudPad\Core\Session\SNRSessionStore;

/**
 * Action: snr-search (Search & Replace)
 * R5: Session writes now go through SNRSessionStore (typed, no raw NativeSession).
 */
function snr_search($builder): void
{
    $repository         = Request::require('repository');
    $search             = Request::getString('search');
    $replace            = Request::getString('replace');
    $batchSnr           = Request::getString('batch-snr');
    $caseSensitive      = Request::getBool('case-sensitive');
    $searchByFilename   = Request::getBool('search-by-filename');
    $forceReplace       = Request::getBool('force-replace');
    $forceDelete        = Request::getBool('force-delete');
    $confirmDelete      = Request::getBool('confirm-delete');
    $maxFilesReturned   = Request::getInt('max-files-returned', 20);
    $fileMask           = Request::getString('file-mask');

    // Persist UI state so the SNR tab restores correctly on next render
    $builder->get(SNRSessionStore::class)->saveSearchState(
        $repository, $search, $fileMask,
        $maxFilesReturned, $replace, $batchSnr, !empty($batchSnr)
    );

    $snr = $builder->get(SNRService::class);

    if (!empty($batchSnr)) {
        // Batch mode: each line is a "search => replace" pair
        $lines = preg_split("/\n/", $batchSnr, -1, PREG_SPLIT_NO_EMPTY);
        $pairs = [];

        foreach ($lines as $line) {
            $parts = explode('=>', $line, 2);
            if (count($parts) < 2) continue;
            $s = trim($parts[0]);
            $r = trim($parts[1]);
            if ($s !== $r) {
                $pairs[$s] = $r;
            }
        }

        foreach ($pairs as $s => $r) {
            $snr->search(
                $repository, $s, !$caseSensitive, $fileMask,
                $maxFilesReturned, $searchByFilename,
                $forceReplace, $r, $forceDelete && $confirmDelete
            );
        }
    } else {
        $snr->search(
            $repository, $search, !$caseSensitive, $fileMask,
            $maxFilesReturned, $searchByFilename,
            $forceReplace, $replace, $forceDelete && $confirmDelete
        );
    }
}
