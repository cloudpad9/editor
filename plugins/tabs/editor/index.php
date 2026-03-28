<?php
class plugin_tab_editor extends \CloudPad\Plugin\BaseTabPlugin
{
    public function getTabTitle(): string
    {
        return 'EDITOR';
    }

    public function getPluginInfo(): ?array
    {
        return ['title' => 'Editor', 'category' => 'Development', 'description' => 'Editing files online'];
    }

    public function render($builder): void
    {
        include __DIR__ . '/index.tpl';
    }
}
