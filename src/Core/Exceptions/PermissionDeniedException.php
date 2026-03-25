<?php
namespace CloudPad\Core\Exceptions;

/**
 * Thrown when the current user lacks permission for the requested operation.
 * Router maps this → 403-style fail response.
 */
class PermissionDeniedException extends \RuntimeException {}
