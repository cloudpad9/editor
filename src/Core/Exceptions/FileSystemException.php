<?php
namespace CloudPad\Core\Exceptions;

/**
 * Thrown when a filesystem operation fails (read, write, chmod, mkdir, etc.).
 * Router maps this to a 500-style fail response.
 */
class FileSystemException extends \RuntimeException {}
