<?php if (\CloudPad\Builder::hasPermission('editor')) : ?>
<div id="editor" style="height:100%;display: flex;flex-direction: column;">
    <div class="editor-file-bar commandbar">
        <form data-success="setEditorContent" data-verbose="0" action="index.php" method="POST" enctype="multipart/form-data" style="float:left;">
            <input type="hidden" name="action" value="open-file-by-name"/>
            <div class="moz-select-wrapper">
                <select v-model="repository" name="repository" class="repositories js-repository" @change="onChangeRepository">
                    <?php $repositories = $builder->getRepositories(); ?>

                    <?php foreach ($repositories as $code => $settings) : ?>
                        <option value="<?php echo $code; ?>" <?php echo session_get('repository') == $code ? 'selected' : ''; ?>><?php echo $settings['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="text" class="filename js-filename" name="filename" value="" placeholder="Enter a filename to open"/>
        </form>
        <form class="tmp-hidden" action="index.php" method="POST" enctype="multipart/form-data" style="float: left;">
            <input type="hidden" name="action" value="editor-close-all"/>
            <input type="submit" onclick="dev_editor.editorCloseAllFiles();return false;" value="Close all"/>
        </form>
        <form class="tmp-hidden" action="index.php" method="POST" enctype="multipart/form-data" style="float: left;">
            <input type="hidden" name="action" value="editor-clear-recents"/>
            <input type="submit" value="Clear recents"/>
        </form>
        <button onclick="dev_editor.editorNewFile()" style="float: left;">New</button>
        <button onclick="dev_editor.editorCloneFile()" style="float: left;">Clone</button>
        <button onclick="dev_editor.editorRecoverFile()" style="float: left;">Recover</button>
        <button onclick="dev_editor.editorRenameFile()" style="float: left;">Rename</button>
        <button onclick="dev_editor.editorRevertFile()" style="float: left;">Revert</button>
        <button onclick="dev_editor.editorReloadFile()" style="float: left;">Reload</button>
        <button onclick="dev_editor.editorBeautifyFile()" style="float: left;">Beautify</button>
        <button class="tmp-hidden" onclick="dev_editor.editorSetCurrentTabColor()" style="float: left;">Color</button>
        <button onclick="dev_editor.editorSyncFile()" style="float: left;">Sync</button>
        <button onclick="dev_editor.editorRevertSyncFile()" style="float: left;">Revert sync</button>
        <form action="index.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="rebuild-filepaths-indexes"/>
            <input type="hidden" name="repository" class="js-mirror-repository" value=""/>
            <input type="submit" onclick="$('.js-mirror-repository').val($('.js-repository').val())" value="Rebuild indexes"/>
        </form>
        <div style="clear:both"></div>
    </div>

    <div id="dev_editor" class="editor-tabs" style="display: flex;flex-direction: column;">
        <ul class="js-tabs-nav">

        </ul>
        <div style="flex:1;display:flex;flex-direction: column;">
            <div class="pane" style="display:flex;flex: 1">
                <div class="js-tabs-container" style="flex:1;">

                </div>
                <div id="dev_editor_sidebar">
                    <div id="explorer" style="height:100%;display: flex;flex-direction: column;">
                        <div class="toolbar" v-if="directoryStructure.length">
                            <input type="file" multiple ref="files" style="display: none" @change="onFilesSelected">
                            <explorer-files-search
                                :repository="repository"
                                :root-node="currentDirectoryNode"
                                @item-clicked="onItemClicked($event);expandToNode($event)"
                            ></explorer-files-search>
                            <span @click="onClickNewFile" title="Add new file">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20"><path d="M216-144q-29.7 0-50.85-21.15Q144-186.3 144-216v-528q0-29.7 21.15-50.85Q186.3-816 216-816h312v72H216v528h528v-312h72v312q0 29.7-21.15 50.85Q773.7-144 744-144H216Zm120-144v-72h288v72H336Zm0-108v-72h288v72H336Zm0-108v-72h288v72H336Zm336-96v-72h-72v-72h72v-72h72v72h72v72h-72v72h-72Z"/></svg>
                            </span>
                            <span @click="onClickNewDirectory" title="Add new folder">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20"><path d="M576-324h72v-72h72v-72h-72v-72h-72v72h-72v72h72v72ZM168-192q-29 0-50.5-21.5T96-264v-432q0-29.7 21.5-50.85Q139-768 168-768h216l96 96h312q29.7 0 50.85 21.15Q864-629.7 864-600v336q0 29-21.15 50.5T792-192H168Zm0-72h624v-336H450l-96-96H168v432Zm0 0v-432 432Z"/></svg>
                            </span>
                            <span @click="onClickUploadFiles" title="Upload files">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20"><path d="M444-336v-342L339-573l-51-51 192-192 192 192-51 51-105-105v342h-72ZM263.717-192Q234-192 213-213.15T192-264v-72h72v72h432v-72h72v72q0 29.7-21.162 50.85Q725.676-192 695.96-192H263.717Z"/></svg>
                            </span>
                            <component v-for="plugin in plugins" :is="plugin" context="toolbar" :data="pluginData" :open="openNode" :expand="expandDirectoryNode"></component>
                        </div>
                        <ul class="file-tree" style="max-height: 75vh;">
                            <template v-if="directoryStructure.length">
                                <file-item :item="item" :current="currentDirectoryNode" :key="item.id" v-for="item in directoryStructure" @item-clicked="onItemClicked" @item-context="onItemContext"/>
                            </template>
                        </ul>
                        <text-search-panel
                            v-if="currentDirectoryNode && currentDirectoryNode.path && currentDirectoryNode.path !== 'quick-access://'"
                            :repository="currentDirectoryNode.repository"
                            :path="currentDirectoryNode.path">
                        </text-search-panel>
                    </div>
                </div>
            </div>
            <div v-if="false" class="pane terminal-wrapper" :class="{full: terminalFullMode}" @dblclick="toggleTerminalMode">
                <div class="gutter gutter-h"></div>
                <div style="position: relative">
                    <div id="terminal"></div>
                    <div class="terminal-error" v-if="terminalError" v-html="terminalError"></div>
                </div>
            </div>
        </div>
        <context-menu ref="contextmenu">
            <ul v-if="contextNode">
                <template v-if="contextNode.isDir">
                    <li @click="onClickNewFile">New file...</li>
                    <li @click="onClickNewDirectory">New folder...</li>
                    <li class="sep"></li>
                    <li @click="onClickRenameNode(contextNode)">Rename...</li>
                    <li class="sep"></li>
                    <li @click="onClickCopyPathToClipboard(contextNode)">Copy path to clipboard</li>
                    <li class="sep"></li>
                    <li @click="onClickOpenFileLocation(contextNode)">Open directory location</li>
                    <li class="sep"></li>
                    <li @click="onClickGitStatus(contextNode)">Git status</li>
                    <li @click="onClickGitLog(contextNode)">Git log</li>
                    <li class="sep"></li>
                    <li v-if="!contextNode.quickAccess" @click="onClickPinToQuickAccess(contextNode)">Pin to Quick access</li>
                    <li v-else @click="onClickUnpinFromQuickAccess(contextNode)">Unpin from Quick access</li>
                    <li class="sep"></li>
                    <li @click="onClickDownloadDirectory(contextNode)">Download</li>
                    <li v-if="markedNodes.length" class="sep"></li>
                    <li v-if="markedNodes.length" @click="onClickMoveToDirectory(contextNode)">Move here</li>
                    <li v-if="markedNodes.length" class="sep"></li>
                    <li v-if="markedNodes.length" @click="onClickCopyToDirectory(contextNode)">Paste</li>
                </template>
                <template v-else>
                    <li @click="onClickDeleteFile(contextNode)">Delete...</li>
                    <li class="sep"></li>
                    <li @click="onClickRenameNode(contextNode)">Rename...</li>
                    <li class="sep"></li>
                    <li @click="onClickCloneFile(contextNode)">Clone...</li>
                    <li class="sep"></li>
                    <li @click="onClickCopyPathToClipboard(contextNode)">Copy path to clipboard</li>
                    <li class="sep"></li>
                    <li @click="onClickOpenFileLocation(contextNode)">Open file location</li>
                    <li class="sep"></li>
                    <li @click="onClickGitStatus(contextNode)">Git status</li>
                    <li @click="onClickGitLog(contextNode)">Git log</li>
                    <li class="sep"></li>
                    <li v-if="!contextNode.quickAccess" @click="onClickPinToQuickAccess(contextNode)">Pin to Quick access</li>
                    <li v-else @click="onClickUnpinFromQuickAccess(contextNode)">Unpin from Quick access</li>
                    <li class="sep"></li>
                    <li @click="onClickDownloadFile(contextNode)">Download</li>
                    <li class="sep"></li>
                    <li v-if="!contextNode.marked" @click="onClickMarkToMoveOrCopy(contextNode)">Mark to move</li>
                    <li class="sep"></li>
                    <li v-if="!contextNode.marked" @click="onClickMarkToMoveOrCopy(contextNode)">Copy</li>
                    <li v-else @click="onClickUnmark(contextNode)">Unmark</li>
                </template>
                <component v-for="plugin in plugins" :is="plugin" context="contextmenu" :data="pluginData" :open="openNode" :expand="expandDirectoryNode"></component>
            </ul>
        </context-menu>
        <input-dialog ref="inputDialog"></input-dialog>
        <command-center></command-center>
        <inline-ace-editor></inline-ace-editor>
        <git-diff-viewer></git-diff-viewer>
        <git-status-viewer></git-status-viewer>
        <apply-patch-modal></apply-patch-modal>
        <console-output-viewer></console-output-viewer>
        <search-workspace></search-workspace>
    </div>

    <script type="text/javascript">
        $(function() {
            window.dev_editor = new Editor({
                el: '#dev_editor',
                files: <?php echo json_encode($builder->getEditorOpenFiles()); ?>,
                style: 2
            })

            initSplitPanes(document.getElementById('dev_editor'), {
                minHeight: 30
            })
        })
    </script>

    <script>
        window.editorEventBus = new Vue();

        <?php foreach ([
            'input-dialog',
            'inline-ace-editor',
            'command-center',
            'git-diff-viewer',
            'git-status-viewer',
            'console-output-viewer',
            'apply-patch-modal',
            'context-menu',
            'text-search-panel',
            'search-workspace',
            'file-item',
            'explorer-files-search',
            'hugo-plugin',
            'explorer-app',
        ] as $component) : ?>
        <?php include __DIR__ . '/components/' . $component . '.js'; ?>
        <?php endforeach; ?>
    </script>

</div>
<?php endif; ?>
