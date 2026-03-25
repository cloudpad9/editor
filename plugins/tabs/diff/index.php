<?php
class plugin_tab_diff {
    function getTabTitle() {
        return 'DIFF';
    }

    function getPluginInfo() {
        return array('title' => 'Diff', 'category' => 'Development', 'description' => 'Compare strings for differences');
    }

    function render($builder) {
        include(dirname(__FILE__).'/index.tpl');
    }
}
