<?php
namespace CloudPad\Core;

/**
 * Request — Wrapper truy cập $_REQUEST có validation.
 *
 * Quy ước:
 *   - KHÔNG truy cập $_REQUEST trực tiếp trong business logic
 *   - Luôn dùng Request::getString(), Request::require(), v.v.
 *   - Mọi input đều được trim() trước khi trả về
 */
class Request
{
    /**
     * Lấy giá trị string, trả về $default nếu không có.
     */
    public static function getString(string $key, string $default = ''): string
    {
        return trim((string)($_REQUEST[$key] ?? $default));
    }

    /**
     * Lấy giá trị string, trả Response::fail() nếu thiếu hoặc rỗng.
     */
    public static function require(string $key): string
    {
        $value = trim((string)($_REQUEST[$key] ?? ''));

        if ($value === '') {
            Response::fail("Missing required parameter: {$key}");
        }

        return $value;
    }

    /**
     * Lấy giá trị integer.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        return (int)($_REQUEST[$key] ?? $default);
    }

    /**
     * Lấy giá trị boolean (hỗ trợ "true"/"false"/"1"/"0").
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        if (!isset($_REQUEST[$key])) {
            return $default;
        }

        return filter_var($_REQUEST[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Lấy mảng từ $_REQUEST key (nếu là string thì explode theo delimiter).
     *
     * @param string $delimiter  Dùng để explode nếu value là string (mặc định: dấu phẩy)
     */
    public static function getArray(string $key, string $delimiter = ',', array $default = []): array
    {
        $value = $_REQUEST[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return array_map('trim', $value);
        }

        return array_filter(array_map('trim', explode($delimiter, (string)$value)));
    }

    /**
     * Kiểm tra request có phải AJAX không.
     */
    public static function isAjax(): bool
    {
        return (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == 1)
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Lấy action hiện tại.
     */
    public static function getAction(): string
    {
        return trim((string)($_REQUEST['action'] ?? ''));
    }

    /**
     * Kiểm tra request có từ mobile device không.
     * Tách từ Builder::isMobile() (Phase 14).
     */
    public static function isMobile(): bool
    {
        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $host = $_SERVER['HTTP_HOST']       ?? '';
        return (bool)(preg_match('/(iphone|ipad|android)/i', $ua)
            || preg_match('/^m\./i', $host));
    }

    /**
     * Trả về tất cả $_REQUEST đã sanitize cơ bản (strip tags).
     * Dùng khi cần log hoặc debug — không dùng cho business logic.
     */
    public static function all(): array
    {
        return array_map(function ($v) {
            return is_string($v) ? trim(strip_tags($v)) : $v;
        }, $_REQUEST);
    }
}
