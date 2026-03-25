<?php
namespace CloudPad\Core\Exceptions;

/**
 * Thrown when request input fails validation (missing required field, bad format, etc.).
 * Router maps this → 422-style fail response.
 */
class ValidationException extends \RuntimeException {}
