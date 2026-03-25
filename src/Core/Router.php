<?php
namespace CloudPad\Core;

use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\PermissionDeniedException;
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\FileSystemException;

/**
 * Router — Action dispatcher cho CloudPad.
 *
 * Tất cả actions đã được migrate sang plugins/commands/ (Phase 2 hoàn tất).
 * Router chỉ cần:
 *   1. Xử lý standalone editor
 *   2. Delegate sang is_plugin_command()
 *   3. Catch exceptions từ plugin commands và trả JSON error chuẩn
 *   4. Log unknown actions
 *
 * Phase 6: Exception-based error handling.
 * Plugin commands nên throw exception thay vì echo/exit trực tiếp.
 */
class Router
{
    /** @var \Builder */
    private $builder;

    public function __construct($builder)
    {
        $this->builder = $builder;
    }

    /**
     * Dispatch action chính.
     *
     * @param string $action     Action từ $_REQUEST['action']
     * @param string $standalone Standalone mode từ $_REQUEST['standalone']
     */
    public function dispatch(string $action, string $standalone = ''): void
    {
        // Special case: standalone editor
        if ($action === 'open-file' && !empty($standalone)) {
            $this->builder->standalone_editor();
            exit(0);
        }

        // Tất cả actions → plugin commands trong plugins/commands/
        try {
            if ($this->builder->is_plugin_command($action, $handler, $methodname)) {
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

        // Unknown action — chỉ log, không crash (non-ajax request vẫn render UI)
        if (!empty($action)) {
            $this->builder->verbose("[Router] Unknown action: {$action}");
        }
    }
}
