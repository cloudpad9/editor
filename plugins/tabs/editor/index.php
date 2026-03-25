<?php
class plugin_tab_editor {
    function getTabTitle() {
        return 'EDITOR';
    }

    function getPluginInfo() {
        return array('title' => 'Editor', 'category' => 'Development', 'description' => 'Editing files online');
    }

    function render($builder) {
        include(dirname(__FILE__).'/index.tpl');
    }
}
