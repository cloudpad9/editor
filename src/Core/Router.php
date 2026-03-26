<?php
namespace CloudPad\Core;

use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\PermissionDeniedException;
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * Router — Action dispatcher cho CloudPad.
 *
 * Phase 6:  Exception-based error handling.
 * Phase 14: Tách is_plugin_command() + standalone_editor() vào Router.
 *           Builder không còn routing logic.
 */
class Router
{
    /** @var \Builder */
    private $builder;

    /** Absolute path tới thư mục plugins/commands/ */
    private string $pluginsDir;

    /** Absolute path tới thư mục gốc app (BUILDER_DIR) */
    private string $appDir;

    public function __construct($builder)
    {
        $this->builder    = $builder;
        $this->appDir     = defined('BUILDER_DIR') ? BUILDER_DIR : dirname(dirname(__DIR__));
        $this->pluginsDir = $this->appDir . '/plugins/commands';
    }

    // ── Main dispatch ─────────────────────────────────────────────────────────

    public function dispatch(string $action, string $standalone = ''): void
    {
        // Special case: standalone editor
        if ($action === 'open-file' && !empty($standalone)) {
            $this->renderStandaloneEditor();
            exit(0);
        }

        try {
            $resolved = $this->resolvePluginCommand($action);

            if ($resolved !== null) {
                [$handler, $methodname] = $resolved;

                if (is_object($handler)) {
                    $handler->$methodname($this->builder);
                } else {
                    $handler($this->builder);
                }
                return;
            }
        } catch (NotFoundException $e) {
            Response::fail($e->getMessage(), ['code' => 404]);
        } catch (PermissionDeniedException $e) {
            Response::fail($e->getMessage(), ['code' => 403]);
        } catch (ValidationException $e) {
            Response::fail($e->getMessage(), ['code' => 422]);
        } catch (FileSystemException $e) {
            error_log('[CloudPad] FileSystemException: ' . $e->getMessage());
            Response::fail($e->getMessage(), ['code' => 500]);
        } catch (\Throwable $e) {
            error_log('[CloudPad] Unhandled exception in action "' . $action . '": ' . $e->getMessage());
            Response::fail('Internal error', ['code' => 500]);
        }

        if (!empty($action)) {
            $this->builder->verbose("[Router] Unknown action: {$action}");
        }
    }

    // ── Plugin command resolution ─────────────────────────────────────────────

    /**
     * Tìm plugin command handler cho $commandPath.
     *
     * Tách từ Builder::is_plugin_command() (Phase 14).
     *
     * @return array{0: object|string, 1: string}|null  [handler, methodname] hoặc null nếu không tìm thấy
     */
    public function resolvePluginCommand(string $commandPath): ?array
    {
        if (!preg_match('/^[a-z0-9_\-\.\/]+$/is', $commandPath)) {
            return null;
        }

        $parts      = explode('/', str_replace('-', '_', $commandPath));
        $command    = array_pop($parts);
        $commandDir = implode('/', $parts);

        // Locate PHP file
        if (!empty($commandDir)) {
            $filepath = $this->pluginsDir . "/{$commandDir}/{$command}.php";
        } else {
            $filepath = $this->pluginsDir . "/{$command}.php";
        }

        $useIndexFile = false;

        if (!file_exists($filepath)) {
            // Try index.php fallback
            if (!empty($commandDir)) {
                $filepath = $this->pluginsDir . "/{$commandDir}/index.php";
            } else {
                $commandDir = $command;
                $command    = 'index';
                $filepath   = $this->pluginsDir . "/{$commandDir}/index.php";
            }

            if (!file_exists($filepath)) {
                return null;
            }

            $useIndexFile = true;
        }

        require_once $filepath;

        $funcname = str_replace(['-', '.', '/'], '_', $commandPath);

        if ($useIndexFile) {
            $classname  = 'plugin_command_' . str_replace(['-', '.', '/'], '_', $commandDir);
            $methodname = $command;
        } else {
            $classname  = 'plugin_command_' . str_replace(['-', '.', '/'], '_', $commandPath);
            $methodname = 'execute';
        }

        if (class_exists($classname)) {
            $handler = new $classname();

            if (!method_exists($handler, $methodname)) {
                $this->builder->verbose(
                    "[ERROR] Class '{$classname}' should declare method `{$methodname}()`"
                );
                return null;
            }

            return [$handler, $methodname];
        }

        if (function_exists($funcname)) {
            return [$funcname, ''];
        }

        $this->builder->verbose(
            "[ERROR] " . basename($filepath) . " should declare a class '{$classname}' or a function '{$funcname}'"
        );

        return null;
    }

    // ── Standalone editor ─────────────────────────────────────────────────────

    /**
     * Render standalone editor template.
     * Tách từ Builder::standalone_editor() (Phase 14).
     */
    private function renderStandaloneEditor(): void
    {
        $filename   = Request::getString('filename');
        $repository = Request::getString('repository');

        $builder = $this->builder; // expose cho template
        include $this->appDir . '/tpl/standalone_editor.tpl';
    }
}
