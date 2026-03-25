<?php
namespace CloudPad\Core\Exceptions;

/**
 * Thrown when a requested resource (file, repository, path) does not exist.
 * Router maps this → 404-style fail response.
 */
class NotFoundException extends \RuntimeException {}
