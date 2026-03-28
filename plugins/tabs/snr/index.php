<?php
class plugin_tab_snr extends \CloudPad\Plugin\BaseTabPlugin
{
    public function getTabTitle(): string
    {
        return 'SEARCH';
    }

    public function getPluginInfo(): ?array
    {
        return ['title' => 'Search & Replace', 'category' => 'Development', 'description' => ''];
    }

    public function render($builder): void
    {
        include __DIR__ . '/index.tpl';
    }
}
