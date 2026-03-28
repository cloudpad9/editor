<?php
class plugin_tab_diff extends \CloudPad\Plugin\BaseTabPlugin
{
    public function getTabTitle(): string
    {
        return 'DIFF';
    }

    public function getPluginInfo(): ?array
    {
        return ['title' => 'Diff', 'category' => 'Development', 'description' => 'Compare strings for differences'];
    }

    public function render($builder): void
    {
        include __DIR__ . '/index.tpl';
    }
}
