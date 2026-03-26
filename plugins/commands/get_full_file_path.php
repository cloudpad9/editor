<?php
use CloudPad\Core\Exceptions\NotFoundException;

/**
 * get_full_file_path — Trả absolute path của file trong repository.
 * Phase 6.3: $builder->json_response() → throw exceptions
 */
function get_full_file_path($builder) {
    $path       = \CloudPad\Core\Request::getString('path');
    $repository = \CloudPad\Core\Request::getString('repository');

    $absPath = $builder->getAbsolutePath($path, $repository);

    if (empty($absPath)) {
        throw new NotFoundException('Path not found.');
    }

    json_ok(['path' => $absPath]);
}
