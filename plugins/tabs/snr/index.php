<?php
class plugin_tab_snr {
    function getTabTitle() {
        return 'SEARCH';
    }

    function getPluginInfo() {
        return array('title' => 'Search & Replace', 'category' => 'Development', 'description' => '');
    }

    function render($builder) {
        include(dirname(__FILE__).'/index.tpl');
    }
}
