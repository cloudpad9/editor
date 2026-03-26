<?php
namespace CloudPad\Core\Session;

/**
 * NativeSession — Concrete session implementation wrapping $_SESSION.
 *
 * Phase 11: Drop-in replacement cho $_SESSION direct access.
 * Singleton — một instance dùng chung toàn app.
 */
class NativeSession implements SessionInterface
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function all(): array
    {
        return $_SESSION ?? [];
    }
}
