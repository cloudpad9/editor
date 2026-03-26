<?php
namespace CloudPad\Plugin;

/**
 * BaseTabPlugin — Base class cho tất cả tab plugins.
 *
 * Tách từ class `plugin_tab` trong index.php (Phase 8.2).
 * Concrete implementations: plugin_tab_editor, plugin_tab_snr, plugin_tab_diff.
 */
abstract class BaseTabPlugin
{
    /**
     * Trả metadata của plugin: title, category, description.
     * Override bắt buộc trong subclass.
     *
     * @return array{title: string, category?: string, description?: string}|null
     */
    public function getPluginInfo(): ?array
    {
        return null;
    }

    /**
     * Trả tiêu đề tab hiển thị trên UI.
     */
    abstract public function getTabTitle(): string;

    /**
     * Render nội dung tab.
     * Thường include một .tpl file.
     */
    abstract public function render($builder): void;
}
