<?php
/**
 * Global response helper functions — autoloaded qua composer.
 *
 * Tách từ index.php (Phase 8.4).
 * Code mới nên gọi trực tiếp \CloudPad\Core\Response::ok() / ::fail().
 * Các functions này giữ lại để backward-compat với plugin commands cũ.
 */

if (!function_exists('json_ok')) {
    function json_ok($payload = null, ?string $message = null): void
    {
        \CloudPad\Core\Response::ok($payload, $message);
    }
}

if (!function_exists('json_fail')) {
    function json_fail(string $message, array $extra = []): void
    {
        \CloudPad\Core\Response::fail($message, $extra);
    }
}

if (!function_exists('json_response')) {
    function json_response(array $arr): void
    {
        \CloudPad\Core\Response::json($arr);
    }
}

if (!function_exists('json_success')) {
    /** @deprecated Dùng json_ok() thay thế. */
    function json_success($payload = null, ?string $message = null): void
    {
        \CloudPad\Core\Response::ok($payload, $message);
    }
}

if (!function_exists('session_get')) {
    /**
     * Safe $_SESSION accessor — replaces direct $_SESSION access in templates.
     * R4: Added to remove template coupling to superglobal.
     */
    function session_get(string $key, mixed $default = null): mixed
    {
        return \CloudPad\Core\Session\NativeSession::getInstance()->get($key, $default);
    }
}
