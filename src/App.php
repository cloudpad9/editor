<?php
namespace CloudPad;

use Dotenv\Dotenv;

/**
 * App — Application bootstrap class.
 *
 * Tách từ index.php (Phase 8.6).
 * Chịu trách nhiệm: load .env, define constants, set PHP settings,
 * khởi tạo Builder + Router.
 *
 * Usage (trong index.php mới):
 *   $app = \CloudPad\App::boot(__DIR__);
 *   $app->run();
 */
class App
{
    private static ?self $instance = null;

    private string $appDir;
    private \Builder $builder;

    // ── Singleton boot ────────────────────────────────────────────────────

    public static function boot(string $appDir): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $app         = new self();
        $app->appDir = $appDir;

        $app->loadEnv();
        $app->defineConstants();
        $app->configurePhp();

        self::$instance = $app;
        return $app;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('App::boot() must be called before getInstance().');
        }
        return self::$instance;
    }

    // ── Bootstrap steps ───────────────────────────────────────────────────

    private function loadEnv(): void
    {
        $dotenv = Dotenv::createImmutable($this->appDir);
        $dotenv->load();
    }

    private function defineConstants(): void
    {
        // SSH
        if (!defined('SSH_PORT'))             define('SSH_PORT',             $_ENV['SSH_PORT']             ?? 22);
        if (!defined('SSH_RSA_PRIVATE_FILE')) define('SSH_RSA_PRIVATE_FILE', $_ENV['SSH_RSA_PRIVATE_FILE'] ?? '');
        if (!defined('SSH_RSA_USERNAME'))     define('SSH_RSA_USERNAME',     $_ENV['SSH_RSA_USERNAME']     ?? '');
        if (!defined('SSH_RSA_PASSPHRASE'))   define('SSH_RSA_PASSPHRASE',   $_ENV['SSH_RSA_PASSPHRASE']   ?? '');

        // PHP binary
        if (!defined('PHP_PATH'))             define('PHP_PATH',             $_ENV['PHP_PATH']             ?? '/usr/bin/php');

        // Application
        if (!defined('BUILDER_DIR'))          define('BUILDER_DIR',          $this->appDir);

        // Absolute URL
        if (!defined('BUILDER_ABSOLUTE_URL')) {
            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https:' : 'http:';
            $port   = $_SERVER['SERVER_PORT']  ?? 80;
            $host   = $_SERVER['SERVER_NAME']  ?? 'localhost';
            $suffix = ($port != 80 && $port != 443) ? ':' . $port : '';
            define('BUILDER_ABSOLUTE_URL', $scheme . '//' . $host . $suffix);
        }
    }

    private function configurePhp(): void
    {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush(true);

        // Security headers — thay thế X-XSS-Protection: 0 (Phase 16 preview)
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
        }
    }

    // ── Application run ───────────────────────────────────────────────────

    /**
     * Khởi chạy toàn bộ request lifecycle.
     * Gọi từ index.php sau boot().
     */
    public function run(): void
    {
        $action     = \CloudPad\Core\Request::getAction();
        $standalone = \CloudPad\Core\Request::getString('standalone');
        $ajax       = \CloudPad\Core\Request::isAjax();

        $publicActions = [
            'about',
            'user/register',
            'user/login',
            'user/logout',
            'user/forgot',
            'user/reset_password',
            'user/activate_account',
            'user/googleLogin',
            'user/facebookLogin',
        ];

        $this->builder = new \Builder();
        $this->builder->load_language_file();

        // Auth check
        if (!in_array($action, $publicActions, true)) {
            $this->builder->auth();
            // Phase 9.3: ProcessManager cần userDataDir (available sau auth)
            $this->builder->initProcessManager();
        }

        // Dispatch
        $router = new \CloudPad\Core\Router($this->builder);
        $router->dispatch($action, $standalone);

        // Render UI (non-AJAX, non-download, non-public)
        $renderable = !$ajax
            && !in_array($action, ['download-user-file'], true)
            && !in_array($action, $publicActions, true);

        if ($renderable) {
            $builder = $this->builder; // expose cho tpl
            include $this->appDir . '/tpl/index.tpl';
        }
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function getBuilder(): \Builder
    {
        return $this->builder;
    }

    public function getAppDir(): string
    {
        return $this->appDir;
    }
}
