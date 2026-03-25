<?php
namespace CloudPad\Core;

/**
 * Router — Action dispatcher cho CloudPad.
 *
 * Tất cả actions đã được migrate sang plugins/commands/ (Phase 2 hoàn tất).
 * Router chỉ cần:
 *   1. Xử lý standalone editor
 *   2. Delegate sang is_plugin_command()
 *   3. Log unknown actions
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
        if ($this->builder->is_plugin_command($action, $handler, $methodname)) {
            if (is_object($handler)) {
                $handler->$methodname($this->builder);
            } else {
                $handler($this->builder);
            }
            return;
        }

        // Unknown action — chỉ log, không crash (non-ajax request vẫn render UI)
        if (!empty($action)) {
            $this->builder->verbose("[Router] Unknown action: {$action}");
        }
    }
}
