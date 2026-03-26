<?php
namespace CloudPad\Core\Session;

/**
 * SessionInterface — Abstraction cho PHP session.
 *
 * Phase 11: Loại bỏ $_SESSION trực tiếp trong business logic.
 * Concrete implementation: NativeSession (wraps $_SESSION).
 * Test implementation: ArraySession (in-memory, cho unit tests).
 */
interface SessionInterface
{
    /** Lấy giá trị, trả $default nếu không tồn tại. */
    public function get(string $key, mixed $default = null): mixed;

    /** Ghi giá trị. */
    public function set(string $key, mixed $value): void;

    /** Kiểm tra key tồn tại (và không null). */
    public function has(string $key): bool;

    /** Xoá key. */
    public function remove(string $key): void;

    /** Trả toàn bộ session data. */
    public function all(): array;
}
