<?php
namespace CloudPad\Core;

/**
 * Container — Simple DI Container.
 *
 * Phase 10: Dùng để wire dependencies giữa services,
 * loại bỏ circular dependency Builder ↔ Services.
 *
 * Usage:
 *   $c = new Container();
 *   $c->singleton(MyService::class, fn($c) => new MyService($c->get(Dep::class)));
 *   $service = $c->get(MyService::class);
 */
class Container
{
    /** @var array<string, callable> */
    private array $bindings = [];
    /** @var array<string, object> */
    private array $instances = [];

    /**
     * Đăng ký singleton factory.
     * Factory nhận Container instance để resolve deps.
     */
    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * Resolve một abstract → instance (lazy, singleton).
     * @throws \RuntimeException nếu không có binding
     */
    public function get(string $abstract): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            throw new \RuntimeException("Container: no binding registered for '{$abstract}'.");
        }

        $this->instances[$abstract] = ($this->bindings[$abstract])($this);
        return $this->instances[$abstract];
    }

    /**
     * Kiểm tra có binding cho abstract không.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Đăng ký instance đã tạo sẵn (không cần factory).
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }
}
