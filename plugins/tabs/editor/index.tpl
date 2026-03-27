<?php if (Builder::hasPermission('editor')) : ?>
<div id="editor" style="height:100%;display: flex;flex-direction: column;">
    <div class="editor-file-bar commandbar">
        <form data-success="setEditorContent" data-verbose="0" action="index.php" method="POST" enctype="multipart/form-data" style="float:left;">
            <input type="hidden" name="action" value="open-file-by-name"/>
            <div class="moz-select-wrapper">
                <select v-model="repository" name="repository" class="repositories js-repository" @change="onChangeRepository">
                    <?php $repositories = $builder->getRepositories(); ?>

                    <?php foreach ($repositories as $code => $settings) : ?>
                        <option value="<?php echo $code; ?>" <?php echo isset($_SESSION['repository']) && $_SESSION['repository'] == $code? 'selected' : ''; ?>><?php echo $settings['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="text" class="filename js-filename" name="filename" value="" placeholder="Enter a filename to open"/>
            <!--<input type="submit" value="Open"/>-->
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

        Vue.component('input-dialog', {
            props: {
                title: { type: String, default: 'Input' },
                label: { type: String, default: '' },
                placeholder: { type: String, default: '' },
                hint: { type: String, default: '' },
                confirmText: { type: String, default: 'Confirm' },
                cancelText: { type: String, default: 'Cancel' },
                inputType: { type: String, default: 'text' },
                required: { type: Boolean, default: false },
                validator: { type: Function, default: null }
            },
            template: `
                <div v-if="isOpen"
                     class="input-dialog-overlay"
                     role="dialog"
                     aria-modal="true"
                     :aria-hidden="!isOpen"
                     @click.self="cancel"
                     style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:1300;">

                    <!-- backdrop -->
                    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5);"></div>

                    <!-- modal -->
                    <div class="input-dialog-modal"
                         style="position:relative; max-width:90%; width:500px; background:#fff; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; z-index:1201;">

                        <header style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #eee;">
                            <div style="font-weight:600; font-size:16px;">{{ opts.title }}</div>
                            <button @click="cancel" aria-label="Close" style="font-size:20px; line-height:1; padding:6px 10px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                ×
                            </button>
                        </header>

                        <div style="padding:16px;">
                            <label v-if="opts.label" style="display:block; margin-bottom:8px; font-weight:500; color:#333;">
                                {{ opts.label }}
                            </label>
                            <input
                                ref="input"
                                v-model="inputValue"
                                @keydown.enter="confirm"
                                @keydown.esc="cancel"
                                :type="opts.inputType"
                                :placeholder="opts.placeholder"
                                style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px; font-family:monospace; box-sizing:border-box;"
                                :style="{ borderColor: errorMessage ? '#d73a49' : '#ddd' }"
                            />
                            <div v-if="errorMessage" style="margin-top:6px; font-size:13px; color:#d73a49;">
                                ⚠️ {{ errorMessage }}
                            </div>
                            <div v-else-if="opts.hint" style="margin-top:8px; font-size:13px; color:#6a737d;">
                                💡 {{ opts.hint }}
                            </div>
                        </div>

                        <footer style="display:flex; gap:8px; justify-content:flex-end; padding:12px 16px; border-top:1px solid #eee; background:#fafbfc;">
                            <button @click="cancel" style="padding:8px 16px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer; font-size:14px;">
                                {{ opts.cancelText }}
                            </button>
                            <button @click="confirm" style="padding:8px 16px; border-radius:6px; border:none; background:#0366d6; color:#fff; cursor:pointer; font-size:14px; font-weight:500;">
                                {{ opts.confirmText }}
                            </button>
                        </footer>
                    </div>
                </div>
            `,
            data() {
                return {
                    isOpen: false,
                    inputValue: '',
                    errorMessage: '',
                    resolvePromise: null,
                    rejectPromise: null,
                    opts: {
                        title: this.title,
                        label: this.label,
                        placeholder: this.placeholder,
                        hint: this.hint,
                        confirmText: this.confirmText,
                        cancelText: this.cancelText,
                        inputType: this.inputType,
                        required: this.required,
                        validator: this.validator
                    }
                };
            },
            methods: {
                open(options = {}) {
                    // Merge options vào opts - Vue tự động reactive
                    Object.assign(this.opts, {
                        title: options.title || this.title,
                        label: options.label || this.label,
                        placeholder: options.placeholder || this.placeholder,
                        hint: options.hint || this.hint,
                        confirmText: options.confirmText || this.confirmText,
                        cancelText: options.cancelText || this.cancelText,
                        inputType: options.inputType || this.inputType,
                        required: options.required !== undefined ? options.required : this.required,
                        validator: options.validator || this.validator
                    });

                    this.isOpen = true;
                    this.inputValue = options.defaultValue || '';
                    this.errorMessage = '';

                    this.$nextTick(() => {
                        if (this.$refs.input) {
                            this.$refs.input.focus();
                            this.$refs.input.select();
                        }
                    });

                    return new Promise((resolve, reject) => {
                        this.resolvePromise = resolve;
                        this.rejectPromise = reject;
                    });
                },

                confirm() {
                    const value = this.inputValue.trim();

                    // Validate required
                    if (this.opts.required && !value) {
                        this.errorMessage = 'This field is required';
                        return;
                    }

                    // Custom validator
                    if (this.opts.validator) {
                        const validationResult = this.opts.validator(value);
                        if (validationResult !== true) {
                            this.errorMessage = validationResult || 'Invalid input';
                            return;
                        }
                    }

                    this.isOpen = false;
                    this.errorMessage = '';
                    if (this.resolvePromise) {
                        this.resolvePromise(value);
                    }
                },

                cancel() {
                    this.isOpen = false;
                    this.errorMessage = '';
                    if (this.rejectPromise) {
                        this.rejectPromise(new Error('User cancelled'));
                    }
                },

                onKeyDown(e) {
                    if (!this.isOpen) return;
                    if (e.key === 'Escape' || e.key === 'Esc') {
                        this.cancel();
                    }
                }
            },
            mounted() {
                window.addEventListener('keydown', this.onKeyDown);

                this.$watch('isOpen', (open) => {
                    document.body.style.overflow = open ? 'hidden' : '';
                });
            },
            beforeDestroy() {
                window.removeEventListener('keydown', this.onKeyDown);
                document.body.style.overflow = '';
            }
        });

        Vue.component('inline-ace-editor', {
            template: `
                <div v-if="isOpen"
                     class="inline-ace-editor-overlay"
                     role="dialog"
                     aria-modal="true"
                     :aria-hidden="!isOpen"
                     style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:1250;">

                    <!-- backdrop -->
                    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5);" @click="close"></div>

                    <!-- modal -->
                    <div class="inline-ace-editor-modal"
                         style="position:relative; max-width:96%; max-height:92%; width:1000px; background:#fff; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; z-index:1251;">

                        <!-- Header -->
                        <header style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #eee;">
                            <div style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                {{ title }}
                                <span v-if="dirty" style="color:#ff9800; font-weight:400; margin-left:8px;">(modified)</span>
                            </div>
                            <button @click="close" aria-label="Close"
                                    style="font-size:20px; line-height:1; padding:6px 10px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">×</button>
                        </header>

                        <!-- Body: Editor -->
                        <div style="padding:0; overflow:hidden; flex:1; background:#fafafa; position:relative;">
                            <div v-if="error"
                                 style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#b71c1c; background:#fff;">
                                {{ error }}
                            </div>

                            <div v-show="!error"
                                 :id="'editor-content-' + editorId"
                                 style="width:100%; height:70vh;">
                            </div>

                            <div v-if="loading"
                                 style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.7);">
                                <div style="display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px; background:#fff; border:1px solid #eee;">
                                    <svg width="18" height="18" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <circle cx="25" cy="25" r="20" fill="none" stroke="black" stroke-width="6" stroke-opacity="0.15"/>
                                        <path d="M45 25A20 20 0 0 1 25 5" fill="none" stroke="black" stroke-width="6" stroke-linecap="round">
                                            <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/>
                                        </path>
                                    </svg>
                                    <div>Loading...</div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <footer style="display:flex; justify-content:flex-end; gap:8px; padding:10px 16px; border-top:1px solid #eee; background:#fafafa;">
                            <button @click="save"
                                    :disabled="saving || !editor"
                                    style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                <span v-if="!saving">Save</span>
                                <span v-else>Saving...</span>
                            </button>
                            <button @click="close"
                                    :disabled="saving"
                                    style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                Cancel
                            </button>
                        </footer>
                    </div>
                </div>
            `,
            data() {
                return {
                    isOpen: false,
                    repository: '',
                    path: '',
                    line: 1,
                    loading: false,
                    saving: false,
                    error: '',
                    editor: null,
                    editorId: '',
                    dirty: false
                };
            },
            computed: {
                title() {
                    const p = (this.path || '').trim();
                    const i = p.lastIndexOf('/');
                    return i >= 0 ? p.slice(i + 1) : p;
                }
            },
            methods: {
                async open({ repository, path, line }) {
                    this.repository = repository || '';
                    this.path = path || '';
                    this.line = Number(line) || 1;
                    this.error = '';
                    this.isOpen = true;
                    this.loading = true;
                    this.editorId = 'inline_' + Date.now();
                    this.editor = null;
                    this.dirty = false;

                    try {
                        const { data } = await axios.get('index2.php', {
                            params: {
                                action: 'get-file-content',
                                repository: this.repository,
                                filename: this.path,
                                verbose: 0,
                                ajax: 1
                            }
                        });

                        if (!data || data.success !== true) {
                            this.error = (data && data.message) ? data.message : 'Cannot load file content.';
                            return;
                        }

                        // Render editor sau khi modal đã mount
                        this.$nextTick(() => {
                            const $el = $("#editor-content-" + this.editorId);
                            if (!$el.length) {
                                this.error = 'Editor container not found.';
                                return;
                            }

                            // Khởi tạo Ace bằng util có sẵn
                            convertToAceEditor($el);

                            const editor = $el.data('editor');
                            if (!editor) {
                                this.error = 'Ace editor instance not found.';
                                return;
                            }

                            this.editor = editor;
                            this.installEditorHooks();

                            // Set nội dung
                            editor.setValue(data.content || '', -1);
                            editor.session.getUndoManager().reset();
                            this.dirty = false;

                            // Go to line
                            this.gotoLine(this.line);
                        });
                    } catch (err) {
                        console.error(err);
                        this.error = 'Error fetching file content.';
                    } finally {
                        this.loading = false;
                        // Khóa body scroll khi mở modal
                        document.body.style.overflow = this.isOpen ? 'hidden' : '';
                    }
                },

                gotoLine(lineNo) {
                    if (!this.editor) return;

                    const editor = this.editor;
                    const row = Math.max(0, (Number(lineNo) || 1) - 1);

                    // Di chuyển con trỏ trước
                    editor.gotoLine(row + 1, 0, false);

                    // Đợi 1 frame cho modal render xong, rồi resize + scroll
                    requestAnimationFrame(() => {
                        editor.resize(true);
                        editor.renderer.updateFull(true);

                        // Scroll mạnh tay: cả scrollToLine và scrollCursorIntoView
                        editor.scrollToLine(row, true, true, function() {});
                        editor.renderer.scrollCursorIntoView({ row, column: 0 }, 0.5);
                    });
                },

                installEditorHooks() {
                    if (!this.editor) return;

                    // Đánh dấu dirty khi thay đổi
                    this.editor.session.on('change', () => {
                        this.dirty = true;
                    });
                },

                async save() {
                    if (!this.editor || this.saving) return;
                    this.saving = true;

                    try {
                        const content = this.editor.getValue();

                        const form = new FormData();
                        form.append('content', content);

                        const url =
                            'index2.php?action=save-current-file' +
                            '&repository=' + encodeURIComponent(this.repository) +
                            '&filename=' + encodeURIComponent(this.path) +
                            '&verbose=0&ajax=1';

                        const { data } = await axios.post(url, form);

                        if (data && data.success) {
                            this.dirty = false;
                            if (data.message) window.showMessage && window.showMessage(data.message);
                        } else {
                            const msg = (data && data.message) ? data.message : 'Save failed.';
                            window.showMessage && window.showMessage(msg);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage && window.showMessage('Save error.');
                    } finally {
                        this.saving = false;
                    }
                },

                close() {
                    // Có thể cảnh báo nếu còn dirty (tuỳ chọn). Ở đây đóng luôn để gọn.
                    this.cleanupEditor();
                    this.isOpen = false;
                    this.error = '';
                    document.body.style.overflow = '';
                },

                cleanupEditor() {
                    if (this.editor) {
                        try {
                            // Ace không yêu cầu dispose bắt buộc; giải tham chiếu để GC
                            this.editor = null;
                        } catch (e) {}
                    }
                },

                onKeyDown(e) {
                    if (!this.isOpen) return;
                    if (e.key === 'Escape' || e.key === 'Esc') {
                        this.close();
                    }
                },

                handleOpenInlineEditor(payload) {
                    // payload: { repository, path, line }
                    this.open(payload);
                }
            },
            mounted() {
                window.editorEventBus && window.editorEventBus.$on('open-inline-editor', this.handleOpenInlineEditor);
                window.addEventListener('keydown', this.onKeyDown);
                this.$watch('isOpen', (open) => {
                    document.body.style.overflow = open ? 'hidden' : '';
                });
            },
            beforeDestroy() {
                window.editorEventBus && window.editorEventBus.$off('open-inline-editor', this.handleOpenInlineEditor);
                window.removeEventListener('keydown', this.onKeyDown);
                document.body.style.overflow = '';
                this.cleanupEditor();
            }
        });

        Vue.component('command-center', {
            template: `
                <div aria-hidden="true" style="position:fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index:1300; pointer-events:none;">
                    <!-- loading indicator -->
                    <div v-if="loading"
                         style="pointer-events:auto; display:flex; align-items:center; gap:8px; background:rgba(0,0,0,0.7); color:#fff; padding:8px 12px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.2); font-family:Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; font-size:13px;">
                        <svg width="18" height="18" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle cx="25" cy="25" r="20" fill="none" stroke="white" stroke-width="6" stroke-opacity="0.25"/>
                            <path d="M45 25A20 20 0 0 1 25 5" fill="none" stroke="white" stroke-width="6" stroke-linecap="round">
                                <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/>
                            </path>
                        </svg>
                        <div>{{ loadingMessage }}</div>
                    </div>
                </div>
            `,
            data() {
                return {
                    loading: false,
                    loadingMessage: 'Running...'
                };
            },
            methods: {
                async handleGitDiff({ repository, path } = {}) {
                    this.loading = true;
                    this.loadingMessage = 'Running...';
                    try {
                        const { data } = await axios.post(
                            'index2.php?action=git-diff&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&verbose=0&ajax=1'
                        );

                        if (data.success) {
                            window.editorEventBus.$emit('git-diff-output', {
                                repository,
                                path,
                                diff: data.output || ''
                            });
                        }
                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Error fetching git diff');
                    } finally {
                        this.loading = false;
                    }
                },

                async handleGitDiffAll({ repository, path } = {}) {
                    this.loading = true;
                    this.loadingMessage = 'Running...';
                    try {
                        const { data } = await axios.post(
                            'index2.php?action=git-diff-all&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&verbose=0&ajax=1'
                        );

                        if (data.success) {
                            window.editorEventBus.$emit('git-diff-output', {
                                repository,
                                path,
                                diff: data.output || ''
                            });
                        }
                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Error fetching git diff all');
                    } finally {
                        this.loading = false;
                    }
                },

                async handleGitRevert({ repository, path, callback } = {}) {
                    try {
                        // Sử dụng input-dialog với các options cụ thể
                        const commitHash = await this.$root.$refs.inputDialog.open({
                            title: 'Git Revert',
                            label: 'Enter commit hash:',
                            placeholder: 'e.g., b0b94df (leave empty to revert last commit)',
                            hint: 'You can use the first 7-8 characters of the commit hash',
                            confirmText: 'Revert',
                            cancelText: 'Cancel',
                            required: false,
                            validator: (value) => {
                                // Optional: validate commit hash format
                                if (value && !/^[a-f0-9]{6,40}$/i.test(value)) {
                                    return 'Invalid commit hash format (use hex characters only)';
                                }
                                return true;
                            }
                        });

                        this.loading = true;
                        this.loadingMessage = 'Running...';

                        let url = 'index2.php?action=git-revert&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&verbose=0&ajax=1';

                        if (commitHash && commitHash !== '') {
                            url += '&commit=' + encodeURIComponent(commitHash);
                        }

                        const { data } = await axios.post(url);

                        if (data.success) {
                            window.dev_editor.editorReloadFile();
                            window.editorEventBus.$emit('console-output', data.output || '');
                            window.editorEventBus.$emit('done::git-revert', { repository, path, commitHash });
                            callback && callback();
                        }
                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        if (err.message === 'User cancelled') {
                            console.log('Git revert cancelled by user');
                            return;
                        }
                        console.error(err);
                        window.showMessage('Error running git revert');
                    } finally {
                        this.loading = false;
                    }
                },

                async handleGitRemoveUntracked({ repository, path, callback } = {}) {
                    this.loading = true;
                    this.loadingMessage = 'Running...';
                    try {
                        const { data } = await axios.post(
                            'index2.php?action=git-remove-untracked&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&verbose=0&ajax=1'
                        );

                        if (data.success) {
                            window.dev_editor.editorReloadFile()

                            window.editorEventBus.$emit('console-output', data.output || '');

                            window.editorEventBus.$emit('done::git-remove-untracked', { repository, path });

                            callback && callback()
                        }
                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Error running git remove untracked');
                    } finally {
                        this.loading = false;
                    }
                },

                async handleGitStatus({ repository, path } = {}) {
                    this.loading = true;
                    this.loadingMessage = 'Running...';
                    try {
                        const { data } = await axios.post(
                            'index2.php?action=git-status&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&verbose=0&ajax=1'
                        );

                        if (data.success) {
                            window.editorEventBus.$emit('git-status-output', {
                                repository,
                                path,
                                output: data.output || '',
                                git_root_path: data.git_root_path || '' // backend gửi chuẩn N://...
                            });
                        }
                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Error running git status');
                    } finally {
                        this.loading = false;
                    }
                },

                async handleGitPull({ repository, path } = {}) {
                    this.loading = true;
                    this.loadingMessage = 'Running...';
                    try {
                        const { data } = await axios.post(
                            'index2.php?action=git-pull&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&verbose=0&ajax=1'
                        );

                        if (data.success) {
                            window.editorEventBus.$emit('console-output', data.output || '');

                            window.editorEventBus.$emit('done::git-pull', { repository, path });
                        }
                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Error running git pull');
                    } finally {
                        this.loading = false;
                    }
                },

                async handleGitCommit({ repository, path, callback } = {}) {
                    const message = await this.$root.$refs.inputDialog.open({
                        title: 'Git Commit',
                        label: 'Commit message:',
                        placeholder: '',
                        hint: 'You can leave it empty to use a default message',
                        confirmText: 'Commit',
                        cancelText: 'Cancel',
                        required: false,
                        validator: (value) => {
                            return true;
                        }
                    });

                    this.loading = true;
                    this.loadingMessage = 'Running...';

                    try {
                        const { data } = await axios.post(
                            'index2.php?action=git-commit&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&message=' + encodeURIComponent(message) +
                            '&verbose=0&ajax=1'
                        );

                        if (data.success) {
                            window.editorEventBus.$emit('console-output', data.output || '');

                            window.editorEventBus.$emit('done::git-commit', { repository, path });

                            callback && callback()
                        }
                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Error running git commit');
                    } finally {
                        this.loading = false;
                    }
                },

                async handleGitCommitAll({ repository, path, callback } = {}) {
                    const message = await this.$root.$refs.inputDialog.open({
                        title: 'Git Commit',
                        label: 'Commit message:',
                        placeholder: '',
                        hint: 'You can leave it empty to use a default message',
                        confirmText: 'Commit',
                        cancelText: 'Cancel',
                        required: false,
                        validator: (value) => {
                            return true;
                        }
                    });

                    this.loading = true;
                    this.loadingMessage = 'Running...';

                    try {
                        const { data } = await axios.post(
                            'index2.php?action=git-commit-all&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&message=' + encodeURIComponent(message) +
                            '&verbose=0&ajax=1'
                        );

                        if (data.success) {
                            window.editorEventBus.$emit('console-output', data.output || '');

                            window.editorEventBus.$emit('done::git-commit-all', { repository, path });

                            callback && callback();
                        }
                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Error running git commit-all');
                    } finally {
                        this.loading = false;
                    }
                },

                async handleGitLog({ repository, path } = {}) {
                    this.loading = true;
                    this.loadingMessage = 'Running...';
                    try {
                        const { data } = await axios.post(
                            'index2.php?action=git-log&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&verbose=0&ajax=1'
                        );

                        if (data.success) {
                            window.editorEventBus.$emit('console-output', data.output || '');
                        }
                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Error running git log');
                    } finally {
                        this.loading = false;
                    }
                },

                async handleGitLogAll({ repository, path } = {}) {
                    this.loading = true;
                    this.loadingMessage = 'Running...';
                    try {
                        const { data } = await axios.post(
                            'index2.php?action=git-log-all&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&verbose=0&ajax=1'
                        );

                        if (data.success) {
                            window.editorEventBus.$emit('console-output', data.output || '');
                        }
                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Error running git log all');
                    } finally {
                        this.loading = false;
                    }
                },

                async handleTextSearch({ query, repository, path, options } = {}) {
                    this.loading = true;
                    this.loadingMessage = 'Searching...';
                    try {
                        const { data } = await axios.post(
                            'index2.php?action=search-and-replace&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&query=' + encodeURIComponent(query) +
                            '&case_sensitive=' + encodeURIComponent(options.caseSensitive) +
                            '&regex=' + encodeURIComponent(options.useRegex) +
                            '&limit=' + encodeURIComponent(options.limit) +
                            '&verbose=0&ajax=1'
                        );

                        if (data && data.success) {
                            window.editorEventBus.$emit('search-results', {
                                query,
                                repository,
                                path,
                                options,
                                results: data.results
                            });
                        } else {
                            if (data && data.message) window.showMessage(data.message);
                            window.editorEventBus.$emit('search-results', {
                                query,
                                repository,
                                path,
                                options,
                                results: { summary:{ scanned:0, matched_files:0, total_matches:0 }, matches:[] }
                            });
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Search failed');
                        window.editorEventBus.$emit('search-results', {
                            query,
                            repository,
                            path,
                            options,
                            results: { summary:{ scanned:0, matched_files:0, total_matches:0 }, matches:[] }
                        });
                    } finally {
                        this.loading = false;
                    }
                },

                async handleTextReplace({ query, replacement, repository, path, options } = {}) {
                    this.loading = true;
                    this.loadingMessage = 'Replacing...';
                    try {
                        const { data } = await axios.post(
                            'index2.php?action=search-and-replace&repository=' + encodeURIComponent(repository) +
                            '&path=' + encodeURIComponent(path) +
                            '&query=' + encodeURIComponent(query) +
                            '&replacement=' + encodeURIComponent(replacement) +
                            '&case_sensitive=' + encodeURIComponent(options.caseSensitive) +
                            '&regex=' + encodeURIComponent(options.useRegex) +
                            '&limit=' + encodeURIComponent(options.limit) +
                            '&verbose=0&ajax=1'
                        );

                        if (data && data.success) {
                            window.editorEventBus.$emit('replace-results', {
                                query,
                                replacement,
                                repository,
                                path,
                                options,
                                results: data.results
                            });
                        } else {
                            if (data && data.message) window.showMessage(data.message);
                            window.editorEventBus.$emit('replace-results', {
                                query,
                                replacement,
                                repository,
                                path,
                                options,
                                results: { summary:{ scanned:0, matched_files:0, total_matches:0 }, matches:[] }
                            });
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Replace failed');
                        window.editorEventBus.$emit('replace-results', {
                            query,
                            replacement,
                            repository,
                            path,
                            options,
                            results: { summary:{ scanned:0, matched_files:0, total_matches:0 }, matches:[] }
                        });
                    } finally {
                        this.loading = false;
                    }
                },
                async handleApplyPatch({ repository, path, patch } = {}) {
                    this.loading = true;
                    this.loadingMessage = 'Running...';
                    try {
                        const body = new URLSearchParams();
                        body.set('patch', patch || '');

                        const { data } = await axios.post(
                            'index2.php?action=apply-patch'
                            + '&repository=' + encodeURIComponent(repository || '')
                            + '&path=' + encodeURIComponent(path || '')
                            + '&verbose=0&ajax=1',
                            body,
                            { headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' } }
                        );

                        if (data.success) {
                            window.dev_editor.editorReloadFile()

                            window.editorEventBus.$emit('console-output', data.output);

                            window.editorEventBus.$emit('done::apply-patch', { repository, path });
                        }

                        if (data.message) {
                            window.showMessage(data.message);
                        }
                    } catch (err) {
                        console.error(err);
                        window.showMessage('Error applying patch');
                    } finally {
                        this.loading = false;
                    }
                }

            },
            mounted() {
                window.editorEventBus.$on('git-diff', this.handleGitDiff);
                window.editorEventBus.$on('git-diff-all', this.handleGitDiffAll);
                window.editorEventBus.$on('git-revert', this.handleGitRevert);
                window.editorEventBus.$on('git-remove-untracked', this.handleGitRemoveUntracked);
                window.editorEventBus.$on('git-status', this.handleGitStatus);
                window.editorEventBus.$on('git-pull', this.handleGitPull);
                window.editorEventBus.$on('git-commit', this.handleGitCommit);
                window.editorEventBus.$on('git-commit-all', this.handleGitCommitAll);
                window.editorEventBus.$on('git-log', this.handleGitLog);
                window.editorEventBus.$on('git-log-all', this.handleGitLogAll);
                window.editorEventBus.$on('text-search', this.handleTextSearch);
                window.editorEventBus.$on('text-replace', this.handleTextReplace);
                window.editorEventBus.$on('call::apply-patch', this.handleApplyPatch);
            },
            beforeDestroy() {
                window.editorEventBus.$off('git-diff', this.handleGitDiff);
                window.editorEventBus.$off('git-diff-all', this.handleGitDiffAll);
                window.editorEventBus.$off('git-revert', this.handleGitRevert);
                window.editorEventBus.$off('git-remove-untracked', this.handleGitRemoveUntracked);
                window.editorEventBus.$off('git-status', this.handleGitStatus);
                window.editorEventBus.$off('git-pull', this.handleGitPull);
                window.editorEventBus.$off('git-commit', this.handleGitCommit);
                window.editorEventBus.$off('git-commit-all', this.handleGitCommitAll);
                window.editorEventBus.$off('git-log', this.handleGitLog);
                window.editorEventBus.$off('git-log-all', this.handleGitLogAll);
                window.editorEventBus.$off('text-search', this.handleTextSearch);
                window.editorEventBus.$off('text-replace', this.handleTextReplace);
                window.editorEventBus.$off('call::apply-patch', this.handleApplyPatch);
            }
        });

        Vue.component('git-diff-viewer', {
            template: `
                <div v-if="isOpen"
                     class="git-diff-overlay"
                     role="dialog"
                     aria-modal="true"
                     :aria-hidden="!isOpen"
                     style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:1200;">

                    <!-- backdrop -->
                    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5);"></div>

                    <!-- modal -->
                    <div class="git-diff-modal"
                         style="position:relative; max-width:90%; max-height:90%; width:900px; background:#fff; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; z-index:1201;">

                        <header style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #eee;">
                            <div style="font-weight:600;">Git Diff</div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <button @click="close" aria-label="Close"
                                        style="font-size:20px; line-height:1; padding:6px 10px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                    ×
                                </button>
                            </div>
                        </header>

                        <div style="padding:12px 16px; overflow:auto; flex:1; background:#fff;">
                            <pre v-if="diffContent" v-html="highlightedDiff"
                                 style="white-space:pre-wrap; word-break:break-word; font-family:monospace; font-size:15px; margin:0;">
                            </pre>
                        </div>

                        <!-- Footer: Revert / Commit -->
                        <footer style="display:flex; justify-content:flex-end; gap:8px; padding:10px 16px; border-top:1px solid #eee; background:#fafafa;">
                            <button @click="revertCurrent"
                                    :disabled="!canAct"
                                    title="Revert this file"
                                    style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                Revert
                            </button>
                            <button @click="commitCurrent"
                                    :disabled="!canAct"
                                    title="Commit this file"
                                    style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                Commit
                            </button>
                        </footer>
                    </div>
                </div>
            `,
            data() {
                return {
                    diffContent: '',
                    currentRepository: null,
                    currentPath: null
                };
            },
            computed: {
                isOpen() {
                    return !!this.diffContent;
                },
                canAct() {
                    return !!(this.currentRepository && this.currentPath);
                },
                highlightedDiff() {
                    if (!this.diffContent) return '';
                    return this.diffContent
                        .split('\n')
                        .map(line => {
                            if (line.startsWith('+') && !line.startsWith('+++')) {
                                return `<span style="background:#b5efdb; color:#0a5d3a;">${this.escapeHtml(line)}</span>`;
                            } else if (line.startsWith('-') && !line.startsWith('---')) {
                                return `<span style="background:#ffc4c1; color:#7a1b1b;">${this.escapeHtml(line)}</span>`;
                            } else if (line.startsWith('@@')) {
                                return `<span style="background:#f1f8ff; color:#0366d6; font-weight:600;">${this.escapeHtml(line)}</span>`;
                            } else if (line.startsWith('diff') || line.startsWith('index') || line.startsWith('---') || line.startsWith('+++')) {
                                return `<span style="background:#fafbfc; color:#6a737d; font-style:italic;">${this.escapeHtml(line)}</span>`;
                            }
                            return this.escapeHtml(line);
                        })
                        .join('\n');
                }
            },
            methods: {
                escapeHtml(str) {
                    if (!str) return '';
                    return str.replace(/&/g, '&amp;')
                              .replace(/</g, '&lt;')
                              .replace(/>/g, '&gt;');
                },
                handleGitDiffOutput({ repository, path, diff }) {
                    this.currentRepository = repository;
                    this.currentPath = path;
                    this.diffContent = diff || '';
                },
                close() {
                    this.diffContent = '';
                    this.currentRepository = null;
                    this.currentPath = null;
                },
                onKeyDown(e) {
                    if (!this.isOpen) return;
                    if (e.key === 'Escape' || e.key === 'Esc') {
                        this.close();
                    }
                },
                commitCurrent() {
                    if (!this.canAct) return;
                    window.editorEventBus.$emit('git-commit', {
                        repository: this.currentRepository,
                        path: this.currentPath,
                        callback: this.close
                    });
                },
                revertCurrent() {
                    if (!this.canAct) return;
                    window.editorEventBus.$emit('git-revert', {
                        repository: this.currentRepository,
                        path: this.currentPath,
                        callback: this.close
                    });
                }
            },
            mounted() {
                window.editorEventBus.$on('git-diff-output', this.handleGitDiffOutput);
                window.addEventListener('keydown', this.onKeyDown);
                this.$watch('isOpen', (open) => {
                    document.body.style.overflow = open ? 'hidden' : '';
                });
            },
            beforeDestroy() {
                window.editorEventBus.$off('git-diff-output', this.handleGitDiffOutput);
                window.removeEventListener('keydown', this.onKeyDown);
                document.body.style.overflow = '';
            }
        });

        Vue.component('git-status-viewer', {
            template: `
                <div v-if="isOpen"
                     class="git-status-overlay"
                     role="dialog"
                     aria-modal="true"
                     :aria-hidden="!isOpen"
                     style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:1100;">

                    <!-- Backdrop -->
                    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5);"></div>

                    <!-- Modal -->
                    <div class="git-status-modal"
                         style="position:relative; max-width:90%; max-height:90%; width:900px; background:#fff; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; z-index:1201;">

                        <!-- Header -->
                        <header style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #eee;">
                            <div style="font-weight:600;">Git Status</div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <!-- Copy button with SVG icon -->
                                <button @click="copyToClipboard"
                                        :title="copyButtonTitle"
                                        aria-label="Copy to clipboard"
                                        style="display:flex; align-items:center; justify-content:center; padding:6px 8px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer; transition:background 0.2s;">
                                    <!-- Copy icon (default) -->
                                    <svg v-if="!copied"
                                         width="16"
                                         height="16"
                                         viewBox="0 0 16 16"
                                         fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path d="M10.5 1H3.5C2.67 1 2 1.67 2 2.5V11.5H3.5V2.5H10.5V1ZM12.5 4H6.5C5.67 4 5 4.67 5 5.5V13.5C5 14.33 5.67 15 6.5 15H12.5C13.33 15 14 14.33 14 13.5V5.5C14 4.67 13.33 4 12.5 4ZM12.5 13.5H6.5V5.5H12.5V13.5Z"
                                              fill="currentColor"/>
                                    </svg>
                                    <!-- Check icon (after copied) -->
                                    <svg v-else
                                         width="16"
                                         height="16"
                                         viewBox="0 0 16 16"
                                         fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path d="M6.5 11.5L3 8L4.06 6.94L6.5 9.38L11.94 3.94L13 5L6.5 11.5Z"
                                              fill="#4caf50"/>
                                    </svg>
                                </button>

                                <button @click="close"
                                        aria-label="Close"
                                        style="font-size:20px; line-height:1; padding:6px 10px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">×</button>
                            </div>
                        </header>

                        <!-- Repo path -->
                        <div style="padding: 5px 10px; color:#4caf50;">
                            Repository: <strong>{{ gitRootPath }}</strong>
                        </div>

                        <!-- Body -->
                        <div style="padding:12px 16px; overflow:auto; flex:1; background:#fff; font-family: monospace; font-size:14px;">
                            <div
                                v-for="(line, idx) in lines"
                                :key="idx"
                                style="display:flex; align-items:center; gap:8px; white-space:pre; line-height:1.5;"
                            >
                                <!-- Checkbox -->
                                <input
                                    v-if="fileRelPath(line)"
                                    type="checkbox"
                                    :checked="isSelected(line)"
                                    @change="onToggle(line, $event)"
                                    style="margin:0;"
                                />

                                <!-- Line content -->
                                <div style="flex:1 1 auto; overflow:hidden;">{{ line }}</div>

                                <!-- Actions -->
                                <template v-if="fileRelPath(line)">
                                    <!-- Untracked entries -->
                                    <template v-if="isUntracked(line)">
                                        <span v-if="canOpen(line)" :style="btnStyle" @click="openFile(line)">open</span>
                                        <span :style="btnStyle" class="action-link" @click="commitFile(line)">commit</span>
                                        <span :style="btnStyle" class="action-link" @click="removeUntrackedFile(line)">remove</span>
                                    </template>

                                    <!-- Tracked entries -->
                                    <template v-else>
                                        <span v-if="canOpen(line)" :style="btnStyle" @click="openFile(line)">open</span>
                                        <span v-if="canDiff(line)" :style="btnStyle" class="action-link" @click="diffFile(line)">diff</span>
                                        <span :style="btnStyle" class="action-link" @click="commitFile(line)">commit</span>
                                        <span :style="btnStyle" class="action-link" @click="revertFile(line)">revert</span>
                                    </template>
                                </template>
                            </div>
                        </div>

                        <!-- Footer -->
                        <footer style="display:flex; justify-content:flex-end; gap:8px; padding:10px 16px; border-top:1px solid #eee; background:#fafafa;">
                            <div v-if="selectedCsv">
                                <button @click="revertSelected" title="Revert selected files"
                                        style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                    Revert selected ({{ selectedCount }})
                                </button>
                                <button @click="commitSelected" title="Commit selected"
                                        style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                    Commit selected ({{ selectedCount }})
                                </button>
                            </div>

                            <span style="flex:1"></span>

                            <button @click="refreshStatus" title="Refresh status"
                                    style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                Refresh
                            </button>

                            <button v-if="hasChanges" @click="commitAll"
                                    :title="hasChanges ? 'Commit all changes' : 'No changes to commit'"
                                    style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                Commit all
                            </button>
                        </footer>
                    </div>
                </div>
            `,

            // ------------------------------------------------------------------------
            // State
            // ------------------------------------------------------------------------
            data() {
                return {
                    isOpen: false,
                    repository: '',
                    path: '',
                    gitRootPath: '',
                    output: '',
                    selected: {},
                    copied: false
                };
            },

            // ------------------------------------------------------------------------
            // Derived values
            // ------------------------------------------------------------------------
            computed: {
                btnStyle() {
                    return 'border-radius:4px; padding:0 5px; color:inherit; box-shadow:0 0 2px 0 #999; cursor:pointer;';
                },

                lines() {
                    if (!this.output) return [];
                    return this.output.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
                },

                hasChanges() {
                    return this.lines.some(l => !!this.fileRelPath(l));
                },

                selectedCount() {
                    return Object.keys(this.selected).length;
                },

                selectedCsv() {
                    return Object.keys(this.selected).filter(Boolean).join(',');
                },

                copyButtonTitle() {
                    return this.copied ? 'Copied!' : 'Copy to clipboard';
                }
            },

            // ------------------------------------------------------------------------
            // Methods
            // ------------------------------------------------------------------------
            methods: {
                // ---- Status parsing helpers ----------------------------------------
                isHeader(line) {
                    return !!line && line.startsWith('##');
                },

                isUntracked(line) {
                    return !!line && line.startsWith('??');
                },

                statusCode(line) {
                    if (!line || this.isHeader(line)) return '';
                    return (line + '  ').slice(0, 2); // always 2 chars (XY)
                },

                isDeleted(line) {
                    const code = this.statusCode(line);
                    if (code === '??') return false;
                    return code.indexOf('D') !== -1;
                },

                isDirectory(line) {
                    // Untracked directories typically end with '/', tracked deletions may not.
                    // We only use this to hide "open". It's safe to rely on trailing slash for dirs.
                    const rel = this.fileRelPath(line);
                    return !!rel && /\/$/.test(rel);
                },

                canOpen(line) {
                    // Hide for directories and deleted entries
                    return !!this.fileRelPath(line) && !this.isDirectory(line) && !this.isDeleted(line);
                },

                canDiff(line) {
                    // Hide for deleted entries; allow for files only and not untracked
                    return !!this.fileRelPath(line) && !this.isDirectory(line) && !this.isDeleted(line) && !this.isUntracked(line);
                },

                // ---- Path helpers ---------------------------------------------------
                dequote(s) {
                    if (!s) return s;
                    const t = s.trim();
                    const quoted = (t.startsWith('"') && t.endsWith('"')) || (t.startsWith("'") && t.endsWith("'"));
                    if (!quoted) return t;
                    const inner = t.slice(1, -1);
                    return inner.replace(/\\([\\'"])/g, '$1');
                },

                fileRelPath(line) {
                    if (!line || this.isHeader(line)) return '';
                    if (line.length < 4) return '';
                    // Strip the status code (2 chars + space) or any leading token(s)
                    let body = (line[2] === ' ') ? line.slice(3) : line.replace(/^[^\s]+\s+/, '');
                    if (!body) return '';
                    // Rename lines: "old -> new"
                    if (body.indexOf(' -> ') !== -1) {
                        const parts = body.split(' -> ');
                        body = parts[parts.length - 1].trim();
                    }
                    body = this.dequote(body);
                    return body.trim();
                },

                joinNodePath(root, rel) {
                    if (!root) return rel;
                    const hasProto = root.endsWith('://');
                    if (hasProto) return root + rel.replace(/^\/+/, '');
                    return root.replace(/\/+$/, '') + '/' + rel.replace(/^\/+/, '');
                },

                nodePathFromLine(line) {
                    const rel = this.fileRelPath(line);
                    if (!rel) return '';
                    return this.joinNodePath(this.gitRootPath, rel);
                },

                // ---- Clipboard functionality ---------------------------------------
                async copyToClipboard() {
                    if (!this.output) return;

                    try {
                        await navigator.clipboard.writeText(this.output);
                        this.copied = true;
                        setTimeout(() => {
                            this.copied = false;
                        }, 2000);
                    } catch (err) {
                        // Fallback for older browsers
                        const textarea = document.createElement('textarea');
                        textarea.value = this.output;
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.select();

                        try {
                            document.execCommand('copy');
                            this.copied = true;
                            setTimeout(() => {
                                this.copied = false;
                            }, 2000);
                        } catch (fallbackErr) {
                            console.error('Failed to copy:', fallbackErr);
                        }

                        document.body.removeChild(textarea);
                    }
                },

                // ---- Actions (single item) -----------------------------------------
                openFile(line) {
                    if (!this.canOpen(line)) return;
                    const nodePath = this.nodePathFromLine(line);
                    if (!nodePath) return;
                    if (window.dev_editor && typeof window.dev_editor.editorOpenFile === 'function') {
                        window.dev_editor.editorOpenFile(nodePath, this.repository);
                    }
                },

                diffFile(line) {
                    if (!this.canDiff(line)) return;
                    const nodePath = this.nodePathFromLine(line);
                    if (!nodePath) return;
                    window.editorEventBus.$emit('git-diff', {
                        repository: this.repository,
                        path: nodePath
                    });
                },

                commitFile(line) {
                    const nodePath = this.nodePathFromLine(line);
                    if (!nodePath) return;
                    window.editorEventBus.$emit('git-commit', {
                        repository: this.repository,
                        path: nodePath
                    });
                },

                revertFile(line) {
                    const nodePath = this.nodePathFromLine(line);
                    if (!nodePath) return;
                    window.editorEventBus.$emit('git-revert', {
                        repository: this.repository,
                        path: nodePath
                    });
                },

                removeUntrackedFile(line) {
                    const nodePath = this.nodePathFromLine(line);
                    if (!nodePath) return;
                    window.editorEventBus.$emit('git-remove-untracked', {
                        repository: this.repository,
                        path: nodePath
                    });
                },

                // ---- Selection helpers ---------------------------------------------
                isSelected(line) {
                    const p = this.nodePathFromLine(line);
                    return !!(p && this.selected[p]);
                },

                onToggle(line, e) {
                    const p = this.nodePathFromLine(line);
                    if (!p) return;
                    const checked = !!(e && e.target && e.target.checked);
                    if (checked) this.$set(this.selected, p, true);
                    else this.$delete(this.selected, p);
                },

                // ---- Bulk actions ---------------------------------------------------
                commitSelected() {
                    if (!this.selectedCsv) return;
                    window.editorEventBus.$emit('git-commit', {
                        repository: this.repository,
                        path: this.selectedCsv
                    });
                    this.selected = {};
                },

                revertSelected() {
                    if (!this.selectedCsv) return;
                    window.editorEventBus.$emit('git-revert', {
                        repository: this.repository,
                        path: this.selectedCsv
                    });
                    this.selected = {};
                },

                // ---- Status refresh / lifecycle ------------------------------------
                refreshStatus() {
                    if (!this.isOpen) return;
                    window.editorEventBus.$emit('git-status', {
                        repository: this.repository,
                        path: this.path
                    });
                },

                commitAll() {
                    if (!this.hasChanges) return;
                    window.editorEventBus.$emit('git-commit-all', {
                        repository: this.repository,
                        path: this.path
                    });
                },

                handleGitStatusOutput({ repository, path, output, git_root_path }) {
                    this.repository   = repository || '';
                    this.path         = path || '';
                    this.gitRootPath  = git_root_path || '';
                    this.output       = output || '';
                    this.selected     = {};
                    this.isOpen       = true;
                },

                close() {
                    this.isOpen      = false;
                    this.output      = '';
                    this.repository  = '';
                    this.path        = '';
                    this.gitRootPath = '';
                    this.copied      = false;
                },

                onKeyDown(e) {
                    if (!this.isOpen) return;
                    if (e.key === 'Escape' || e.key === 'Esc') this.close();
                }
            },

            // ------------------------------------------------------------------------
            // Lifecycle
            // ------------------------------------------------------------------------
            mounted() {
                window.editorEventBus.$on('git-status-output',      this.handleGitStatusOutput);
                window.editorEventBus.$on('done::git-commit',       this.refreshStatus);
                window.editorEventBus.$on('done::git-commit-all',   this.refreshStatus);
                window.editorEventBus.$on('done::git-revert',       this.refreshStatus);
                window.editorEventBus.$on('done::git-remove-untracked', this.refreshStatus);
                window.editorEventBus.$on('done::apply-patch',      this.refreshStatus);

                window.addEventListener('keydown', this.onKeyDown);

                this.$watch('isOpen', (open) => {
                    document.body.style.overflow = open ? 'hidden' : '';
                });
            },

            beforeDestroy() {
                window.editorEventBus.$off('git-status-output',      this.handleGitStatusOutput);
                window.editorEventBus.$off('done::git-commit',       this.refreshStatus);
                window.editorEventBus.$off('done::git-commit-all',   this.refreshStatus);
                window.editorEventBus.$off('done::git-revert',       this.refreshStatus);
                window.editorEventBus.$off('done::git-remove-untracked', this.refreshStatus);
                window.editorEventBus.$off('done::apply-patch',      this.refreshStatus);

                window.removeEventListener('keydown', this.onKeyDown);
                document.body.style.overflow = '';
            }
        });

        Vue.component('console-output-viewer', {
            template: `
                <div v-if="isOpen"
                     class="console-output-overlay"
                     role="dialog"
                     aria-modal="true"
                     :aria-hidden="!isOpen"
                     style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:1200;">

                    <!-- backdrop -->
                    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5);"></div>

                    <!-- modal -->
                    <div class="console-output-modal"
                         style="position:relative; max-width:90%; max-height:90%; width:900px; background:#fff; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; z-index:1201;">

                        <header style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #eee;">
                            <div style="font-weight:600;">Console Output</div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <!-- Copy button with SVG icon -->
                                <button @click="copyToClipboard"
                                        :title="copyButtonTitle"
                                        aria-label="Copy to clipboard"
                                        style="display:flex; align-items:center; justify-content:center; padding:6px 8px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer; transition:background 0.2s;">
                                    <!-- Copy icon (default) -->
                                    <svg v-if="!copied"
                                         width="16"
                                         height="16"
                                         viewBox="0 0 16 16"
                                         fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path d="M10.5 1H3.5C2.67 1 2 1.67 2 2.5V11.5H3.5V2.5H10.5V1ZM12.5 4H6.5C5.67 4 5 4.67 5 5.5V13.5C5 14.33 5.67 15 6.5 15H12.5C13.33 15 14 14.33 14 13.5V5.5C14 4.67 13.33 4 12.5 4ZM12.5 13.5H6.5V5.5H12.5V13.5Z"
                                              fill="currentColor"/>
                                    </svg>
                                    <!-- Check icon (after copied) -->
                                    <svg v-else
                                         width="16"
                                         height="16"
                                         viewBox="0 0 16 16"
                                         fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path d="M6.5 11.5L3 8L4.06 6.94L6.5 9.38L11.94 3.94L13 5L6.5 11.5Z"
                                              fill="#4caf50"/>
                                    </svg>
                                </button>

                                <button @click="close"
                                        aria-label="Close"
                                        style="font-size:20px; line-height:1; padding:6px 10px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">
                                    ×
                                </button>
                            </div>
                        </header>

                        <div style="padding:12px 16px; overflow:auto; flex:1; background:#fff;">
                            <pre v-if="consoleOutput" v-html="highlightedDiff"
                                 style="white-space:pre-wrap; word-break:break-word; font-family:monospace; font-size:15px; margin:0;">
                            </pre>
                        </div>
                    </div>
                </div>
            `,
            data() {
                return {
                    consoleOutput: '',
                    copied: false
                };
            },
            computed: {
                isOpen() {
                    return !!this.consoleOutput;
                },
                highlightedDiff() {
                    if (!this.consoleOutput) return '';
                    return this.consoleOutput
                        .split('\n')
                        .map(line => {
                            if (line.startsWith('+') && !line.startsWith('+++')) {
                                return `<span style="background:#b5efdb; color:#0a5d3a;">${this.escapeHtml(line)}</span>`;
                            } else if (line.startsWith('-') && !line.startsWith('---')) {
                                return `<span style="background:#ffc4c1; color:#7a1b1b;">${this.escapeHtml(line)}</span>`;
                            } else if (line.startsWith('@@')) {
                                return `<span style="background:#f1f8ff; color:#0366d6; font-weight:600;">${this.escapeHtml(line)}</span>`;
                            } else if (line.startsWith('diff') || line.startsWith('index') || line.startsWith('---') || line.startsWith('+++')) {
                                return `<span style="background:#fafbfc; color:#6a737d; font-style:italic;">${this.escapeHtml(line)}</span>`;
                            }
                            return this.escapeHtml(line);
                        })
                        .join('\n');
                },
                copyButtonTitle() {
                    return this.copied ? 'Copied!' : 'Copy to clipboard';
                }
            },
            methods: {
                escapeHtml(str) {
                    if (!str) return '';
                    return str.replace(/&/g, '&amp;')
                              .replace(/</g, '&lt;')
                              .replace(/>/g, '&gt;');
                },

                async copyToClipboard() {
                    if (!this.consoleOutput) return;

                    try {
                        await navigator.clipboard.writeText(this.consoleOutput);
                        this.copied = true;
                        setTimeout(() => {
                            this.copied = false;
                        }, 2000);
                    } catch (err) {
                        // Fallback for older browsers
                        const textarea = document.createElement('textarea');
                        textarea.value = this.consoleOutput;
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.select();

                        try {
                            document.execCommand('copy');
                            this.copied = true;
                            setTimeout(() => {
                                this.copied = false;
                            }, 2000);
                        } catch (fallbackErr) {
                            console.error('Failed to copy:', fallbackErr);
                        }

                        document.body.removeChild(textarea);
                    }
                },

                async handleConsoleOutput(consoleOutput) {
                    this.consoleOutput = consoleOutput;
                },

                close() {
                    this.consoleOutput = '';
                    this.copied = false;
                },

                onKeyDown(e) {
                    if (!this.isOpen) return;
                    if (e.key === 'Escape' || e.key === 'Esc') {
                        this.close();
                    }
                }
            },
            mounted() {
                window.editorEventBus.$on('console-output', this.handleConsoleOutput);
                window.addEventListener('keydown', this.onKeyDown);

                this.$watch('isOpen', (open) => {
                    document.body.style.overflow = open ? 'hidden' : '';
                });
            },
            beforeDestroy() {
                window.editorEventBus.$off('console-output', this.handleConsoleOutput);
                window.removeEventListener('keydown', this.onKeyDown);
                document.body.style.overflow = '';
            }
        });

        Vue.component('apply-patch-modal', {
            template: `
                <div v-if="isOpen"
                     class="apply-patch-overlay"
                     role="dialog"
                     aria-modal="true"
                     :aria-hidden="!isOpen"
                     style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:1200;">
                    <!-- backdrop -->
                    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5);" @click="close"></div>

                    <!-- modal -->
                    <div class="apply-patch-modal"
                         style="position:relative; max-width:96%; max-height:92%; width:900px; background:#fff; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; z-index:1201;">
                        <header style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #eee;">
                            <div style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                Apply Patch
                                <span v-if="path" style="font-weight:400; color:#666; margin-left:8px;">→ {{ shortPath }}</span>
                            </div>
                            <button @click="close" aria-label="Close"
                                    style="font-size:20px; line-height:1; padding:6px 10px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">×</button>
                        </header>

                        <div style="padding:12px 16px; background:#fafafa; border-bottom:1px solid #eee; font-size:12px; color:#666;">
                            Paste a unified diff/patch. It will be applied to the file above.
                        </div>

                        <div style="padding:0 16px 16px; flex:1; display:flex; flex-direction:column; gap:8px; background:#fff;">
                            <textarea
                                ref="ta"
                                v-model="patchText"
                                placeholder="@@\n-Hello foo\n+Hello bar ..."
                                style="flex:1; width:100%; resize:vertical; min-height:260px; font-family: 'Roboto Mono', monospace; font-size:13px; line-height:1.4; border:1px solid #ddd; border-radius:6px; padding:10px;"></textarea>
                        </div>

                        <footer style="display:flex; justify-content:flex-end; gap:8px; padding:10px 16px; border-top:1px solid #eee; background:#fafafa;">
                            <button @click="apply" :disabled="!canApply"
                                    style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">Apply</button>
                            <button @click="close"
                                    style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">Cancel</button>
                        </footer>
                    </div>
                </div>
            `,
            data() {
                return {
                    isOpen: false,
                    repository: '',
                    path: '',
                    patchText: ''
                };
            },
            computed: {
                canApply() { return !!this.repository && !!this.path && !!this.patchText; },
                shortPath() {
                    const p = (this.path || '').trim();
                    const i = p.indexOf('://');
                    return i >= 0 ? p.slice(i + 3) : p;
                }
            },
            methods: {
                open({ repository, path }) {
                    this.repository = repository || '';
                    this.path = path || '';
                    this.patchText = '';
                    this.isOpen = true;
                    this.$nextTick(() => {
                        if (this.$refs.ta) this.$refs.ta.focus();
                        document.body.style.overflow = 'hidden';
                    });
                },
                close() {
                    this.isOpen = false;
                    document.body.style.overflow = '';
                },
                apply() {
                    window.editorEventBus && window.editorEventBus.$emit('call::apply-patch', {
                        repository: this.repository,
                        path: this.path,
                        patch: this.patchText
                    });
                    this.close();
                },
                onKeyDown(e) {
                    if (!this.isOpen) return;
                    if (e.key === 'Escape' || e.key === 'Esc') this.close();
                    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') this.apply();
                },
                handleOpen(payload) {
                    this.open(payload || {});
                }
            },
            mounted() {
                window.editorEventBus && window.editorEventBus.$on('apply-patch', this.handleOpen);
                window.addEventListener('keydown', this.onKeyDown);
                this.$watch('isOpen', (open) => { document.body.style.overflow = open ? 'hidden' : ''; });
            },
            beforeDestroy() {
                window.editorEventBus && window.editorEventBus.$off('apply-patch', this.handleOpen);
                window.removeEventListener('keydown', this.onKeyDown);
                document.body.style.overflow = '';
            }
        });

        Vue.component('context-menu', {
            props: [],
            template: `
                <div id="contextmenu" ref="contextmenu" v-if="active" :style="{ left: x, top: y}">
                    <slot></slot>
                </div>
            `,
            data() {
                return {
                    active: false,
                    x: 0,
                    y: 0
                };
            },
            mounted() {
                document.addEventListener('click', this.documentClick);
            },
            beforeDestroy() {
                document.removeEventListener('click', this.documentClick);
            },
            methods: {
                show: function(x, y, bottomUp) {
                    this.active = true

                    this.$nextTick(() => {
                        let largestHeight = window.innerHeight - this.$refs.contextmenu.offsetHeight - (!bottomUp? 25 : 0)
                        let largestWidth = window.innerWidth - this.$refs.contextmenu.offsetWidth - (!bottomUp? 25 : 0)

                        let top = !bottomUp? y : y - this.$refs.contextmenu.offsetHeight
                        let left = x

                        if (top > largestHeight) {
                            top = largestHeight
                        }

                        if (left > largestWidth) {
                            left = largestWidth
                        }

                        this.y = top + 'px'
                        this.x = left + 'px'
                    })
                },
                hide: function(e) {
                    this.active = false
                },
                documentClick: function(e) {
                    if (this.$refs.contextmenu && !this.$refs.contextmenu.contains(e.target)) {
                        this.hide()
                    }
                }
            }
        });

        /**
         * Panel nhập Search / Replace (emit đúng contract)
         */
        Vue.component('text-search-panel', {
            props: {
                repository: { type: String, required: true },
                path:       { type: String, required: true }
            },
            data() {
                return {
                    searchQuery: '',
                    caseSensitive: true,
                    useRegex: false,
                    limit: ''
                };
            },
            methods: {
                emitSearch() {
                    if (!this.path || this.path === 'quick-access://' || !this.searchQuery.trim()) {
                        window.showMessage(!this.path || this.path === 'quick-access://' ? 'Please select a folder/repository to search.' : 'Enter search text.');
                        return;
                    }
                    const payload = {
                        query: this.searchQuery,
                        replacement: this.replaceText ?? '',
                        repository: this.repository,
                        path: this.path,
                        options: {
                            caseSensitive: this.caseSensitive,
                            useRegex: this.useRegex,
                            limit: this.limit
                        }
                    };
                    window.editorEventBus.$emit('text-search', payload);
                },
                onKeydown(e) {
                    if (e.key === 'Enter') this.emitSearch();
                }
            },
            template: `
                <div style="display:flex;flex-direction:column;position:absolute;bottom:0;width:100%;background:#3f3f3ff2;">
                    <div style="display:flex;align-items:center;gap:8px;padding:6px 8px;border-bottom:1px solid gray;">
                        <input
                            placeholder="Search text..."
                            autocomplete="off"
                            class="explorer-files-search-input"
                            style="flex:1;"
                            v-model="searchQuery"
                            @keydown="onKeydown"
                        >
                        <button style="width:76px;" @click="emitSearch">Search</button>
                    </div>

                    <div style="display:flex;align-items:center;gap:16px;padding:6px 8px;color:#ddd;font-size:12px;">
                        <label><input type="checkbox" v-model="caseSensitive"> Case sensitive</label>
                        <label><input type="checkbox" v-model="useRegex"> Regex</label>
                        <label>Limit <input type="text" v-model.number="limit" min="0" title="Max number of matching files" style="width: 30px;background: pink;color: #000;text-align: center;font-size: 12px;"/></label>
                    </div>
                </div>
            `
        });

        const SearchResults = {
            props: ['data', 'query', 'path', 'caseSensitive', 'useRegex', 'replacement', 'repository'],
            emits: ['open'],
            setup(props, { emit }) {
                const collapsed = Vue.ref(new Set());
                const toggle = (path) => {
                    const s = new Set(collapsed.value);
                    s.has(path) ? s.delete(path) : s.add(path);
                    collapsed.value = s;
                };
                const isCollapsed = (path) => collapsed.value.has(path);
                const toRel = (fullPath) => {
                    return fullPath.replace(props.path, '').replace(/^\/+/, '');
                }
                const openFile = (path) => emit('open', path);

                function escapeHtml(s) {
                    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                }
                function escapeRegex(s) {
                    return s.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
                }
                function highlightLiteral(preview, needle, caseSensitive) {
                    if (!needle) return escapeHtml(preview);
                    const src = caseSensitive ? preview : preview.toLowerCase();
                    const pat = caseSensitive ? needle : needle.toLowerCase();
                    let out = '', i = 0, n = pat.length;
                    if (n === 0) return escapeHtml(preview);
                    while (true) {
                        const p = src.indexOf(pat, i);
                        if (p === -1) { out += escapeHtml(preview.slice(i)); break; }
                        out += escapeHtml(preview.slice(i, p));
                        out += `<span class="sr-hit">${escapeHtml(preview.slice(p, p + n))}</span>`;
                        i = p + n;
                    }
                    return out;
                }
                function highlightPreview(preview) {
                    if (!props.query) return escapeHtml(preview);
                    if (props.useRegex) {
                        const flags = props.caseSensitive ? 'g' : 'gi';
                        try {
                            const re = new RegExp(props.query, flags);
                            // highlight trên HTML-escaped
                            return escapeHtml(preview).replace(re, (m) => `<span class="sr-hit">${escapeHtml(m)}</span>`);
                        } catch (e) {
                            return highlightLiteral(preview, props.query, props.caseSensitive);
                        }
                    }
                    return highlightLiteral(preview, props.query, props.caseSensitive);
                }

                // Helper: build a regex from replacement that tolerates backrefs like $1 by expanding to '.*?'
                function buildBackrefTolerantRegex(rep, caseSensitive) {
                    if (!rep) return null;
                    const parts = rep.split(/\$\d+/g).map(p => escapeRegex(p));
                    // if all parts empty, nothing to match
                    if (parts.every(p => p.length === 0)) return null;
                    const pattern = parts.join('.*?'); // allow anything where backrefs would be
                    try {
                        return new RegExp(pattern, caseSensitive ? 'g' : 'gi');
                    } catch (e) {
                        return null;
                    }
                }
                function longestLiteralChunk(rep) {
                    if (!rep) return '';
                    const chunks = rep.split(/\$\d+/g).filter(Boolean);
                    chunks.sort((a,b) => b.length - a.length);
                    return chunks[0] || '';
                }

                function highlightReplacement(previewAfter) {
                    const text = (previewAfter || '').trim();
                    if (!text) return '';
                    const escaped = escapeHtml(text);

                    // Case 1: simple literal replacement
                    if (props.replacement && !props.useRegex) {
                        return highlightLiteral(text, props.replacement, props.caseSensitive)
                            .replace(/class="sr-hit"/g, 'class="sr-repl"'); // reuse logic, swap class
                    }

                    // Case 2: regex replacement w/ possible backrefs
                    if (props.replacement && props.useRegex) {
                        const re = buildBackrefTolerantRegex(props.replacement, props.caseSensitive);
                        if (re) {
                            // apply on escaped text but matching on unescaped requires a different approach:
                            // do the match on raw text, then rebuild HTML by walking matches
                            const raw = text;
                            let out = '';
                            let last = 0;
                            let m;
                            while ((m = re.exec(raw)) !== null) {
                                const start = m.index;
                                const end = start + (m[0]?.length || 0);
                                out += escapeHtml(raw.slice(last, start));
                                out += `<span class="sr-repl">${escapeHtml(raw.slice(start, end))}</span>`;
                                last = end;
                                if (m[0]?.length === 0) re.lastIndex++; // avoid zero-length loops
                            }
                            out += escapeHtml(raw.slice(last));
                            if (out !== escapeHtml(raw)) return out;
                            // Fallback to longest literal chunk if regex matched nothing
                        }
                        const chunk = longestLiteralChunk(props.replacement);
                        if (chunk) {
                            return highlightLiteral(text, chunk, props.caseSensitive)
                                .replace(/class="sr-hit"/g, 'class="sr-repl"');
                        }
                    }

                    // Case 3: no replacement text (deletion) or nothing matched — just return escaped
                    return escaped;
                }

                // Nhận biết replace mode: có tổng replacements hoặc có ít nhất 1 line có preview_after
                const isReplaceMode = Vue.computed(() => {
                    const s = (props.data && props.data.summary) || {};
                    if (typeof s.total_replacements === 'number') return true;
                    const matches = (props.data && props.data.matches) || [];
                    for (const f of matches) {
                        for (const l of (f.lines || [])) {
                            if (l && Object.prototype.hasOwnProperty.call(l, 'preview_after')) return true;
                        }
                    }
                    return false;
                });

                const openInlineEditor = (filePath, lineNo) => {
                    // Dùng event bus chung
                    window.editorEventBus && window.editorEventBus.$emit('open-inline-editor', {
                        repository: props.repository,
                        path: filePath,
                        line: lineNo
                    });
                };

                return { toggle, isCollapsed, openFile, toRel, highlightPreview, highlightReplacement, escapeHtml, isReplaceMode, openInlineEditor };
            },
            template: `
                <div class="sr-root" style="height:100%; overflow:auto; padding:10px; font-family:'Roboto Mono', monospace;">
                    <div style="border-bottom: 1px solid gray; padding-bottom: 5px;">
                        <span>Scanned: {{data.summary.scanned}}</span>
                        <span> · Files matched: {{data.summary.matched_files}}</span>
                        <span> · Total occurrences: {{data.summary.total_matches}}</span>
                        <template v-if="isReplaceMode">
                            <span> · Files changed: {{data.summary.files_changed || 0}}</span>
                            <span> · Total replacements: {{data.summary.total_replacements || 0}}</span>
                        </template>
                    </div>

                    <div v-for="file in data.matches" :key="file.path" style="margin-top:12px;">
                        <div style="font-weight:bold">
                            <span style="cursor:pointer; margin-right:6px;" @click="toggle(file.path)">
                                {{ isCollapsed(file.path) ? '[+]' : '[-]' }}
                            </span>
                            <span>File: </span>
                            <a href="javascript:void(0)" @click="$emit('open', file.path)">{{ toRel(file.path) }}</a>
                        </div>

                        <div v-show="!isCollapsed(file.path)" style="border-left:1px solid #4c4d4d; margin-left:13px;">
                            <div v-for="line in file.lines"
                                 :key="file.path + ':' + line.line"
                                 style="padding-left:8px; margin-bottom:10px; cursor: pointer;"
                                 @click.stop="openInlineEditor(file.path, line.line)">

                                <!-- SEARCH / BEFORE -->
                                <div style="display:flex;">
                                    <div style="white-space: nowrap;">- Line {{line.line}} - </div>
                                    <div style="margin-left:10px; white-space:pre;"
                                         v-html="highlightPreview((line.preview_before ?? line.preview ?? '').trim())"></div>
                                </div>

                                <!-- REPLACE / AFTER -->
                                <div v-if="isReplaceMode && line.preview_after" style="display:flex; margin-top:2px;">
                                    <div style="white-space: nowrap;">&nbsp;&nbsp;Replaced by: </div>
                                    <div style="margin-left:18px; white-space:pre;"
                                         v-html="highlightReplacement(line.preview_after.trim())"></div>
                                </div>
                            </div>
                            <div style="padding-left:8px;">Found {{ file.total }} occurrence<span v-if="file.total > 1">s</span>.</div>
                        </div>
                    </div>
                </div>
            `
        };

        Vue.component('search-workspace', {
            components: { SearchResults },
            template: `
                <div v-if="isOpen"
                     class="search-results-overlay"
                     role="dialog"
                     aria-modal="true"
                     :aria-hidden="!isOpen"
                     style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:1200;">
                    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5);"></div>
                    <div style="position:relative; max-width:90%; max-height:90%; width:960px; background:#fff; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; z-index:1201;">
                        <header style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #eee;">
                            <div style="font-weight:600;">Search and Replace</div>
                            <button @click="close" aria-label="Close" style="font-size:20px; line-height:1; padding:6px 10px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;">×</button>
                        </header>
                        <div style="padding: 5px 10px; color:#4caf50;">Search on: <strong style="color:#4caf50;">{{repository}} &gt; {{noBranchPrefixPath}}</strong></div>
                        <div style="display: flex;padding: 10px 10px;gap: 10px;background: #dbd5af;">
                            <input v-model="query" @keydown.enter.prevent="emitSearch" placeholder="Search for">
                            <div style="display:flex; gap: 10px;">
                                <label><input type="checkbox" v-model="caseSensitive"> Case sensitive</label>
                                <label><input type="checkbox" v-model="useRegex"> Regex</label>
                                <label>Limit <input type="text" v-model.number="limit" min="0" title="Max number of matching files" style="width: 40px;text-align: center;"/></label>
                            </div>
                            <button style="width:76px;" @click="emitSearch">Search</button>
                            <div style="flex:1"></div>
                            <input v-model="replacement" placeholder="Replace with">
                            <button style="width:76px;" @click="emitReplace">Replace</button>
                        </div>
                        <div style="padding:0; overflow:auto; flex:1; background:#fff;">
                        <search-results
                            v-if="results"
                            :data="results"
                            :path="path"
                            :query="searchQuery"
                            :replacement="replacement"
                            :caseSensitive="caseSensitive"
                            :useRegex="useRegex"
                            :repository="repository"
                            @open="openRelPath"></search-results>
                        </div>
                    </div>
                </div>
            `,
            data() {
                return {
                    isOpen: false,
                    searchQuery: '',
                    results: null,
                    query: '',
                    replacement: '',
                    repository: '',
                    path: '',
                    caseSensitive: true,
                    useRegex: false,
                    limit: 0
                };
            },
            computed: {
                noBranchPrefixPath() {
                    const p = (this.path || '').trim();
                    const i = p.indexOf('://');
                    if (i === -1) return p;
                    return p.slice(i + 3).replace(/^\/+/, '');
                }
            },
            methods: {
                handleSearchResults({ repository, path, query, options, results }) {
                    options = options || {}

                    this.repository = repository;
                    this.path = path;
                    this.query = query;
                    this.searchQuery = query;
                    this.caseSensitive = options.caseSensitive;
                    this.useRegex = options.useRegex;
                    this.limit = options.limit;
                    this.results = results || { summary:{ scanned:0, matched_files:0, total_matches:0 }, matches:[] };
                    this.isOpen = true;
                },
                handleReplaceResults({ query, replacement, repository, path, options, results }) {
                    this.query = query;
                    this.searchQuery = query;
                    this.replacement = replacement || '';
                    this.repository = repository;
                    this.path = path;
                    this.caseSensitive = options.caseSensitive;
                    this.useRegex = options.useRegex;
                    this.limit = options.limit;
                    this.results = results || { summary:{ scanned:0, matched_files:0, total_matches:0 }, matches:[] };
                    this.isOpen = true;
                },
                emitSearch() {
                    const lim = Number.isFinite(this.limit) && this.limit > 0 ? this.limit : 0;

                    window.editorEventBus.$emit('text-search', {
                        repository: this.repository,
                        path: this.path,
                        query: this.query,
                        options: {
                            caseSensitive: this.caseSensitive,
                            useRegex: this.useRegex,
                            limit: lim
                        }
                    });
                },
                emitReplace() {
                    window.editorEventBus.$emit('text-replace', {
                        repository: this.repository,
                        path: this.path,
                        query: this.query,
                        replacement: this.replacement,
                        options: {
                            caseSensitive: this.caseSensitive,
                            useRegex: this.useRegex,
                            limit: this.limit
                        }
                    });
                },
                close() {
                    this.isOpen = false;
                    this.results = null;
                },
                onKeyDown(e) {
                    if (!this.isOpen) return;
                    if (e.key === 'Escape' || e.key === 'Esc') this.close();
                },
                openRelPath(relPath) {
                    if (!relPath) return;
                    window.dev_editor && window.dev_editor.editorOpenFile(relPath, this.repository);
                }
            },
            mounted() {
                window.editorEventBus.$on('search-results', this.handleSearchResults);
                window.editorEventBus.$on('replace-results', this.handleReplaceResults);
                window.addEventListener('keydown', this.onKeyDown);
                this.$watch('isOpen', (open) => {
                    document.body.style.overflow = open ? 'hidden' : '';
                });
            },
            beforeDestroy() {
                window.editorEventBus.$off('search-results', this.handleSearchResults);
                window.editorEventBus.$off('replace-results', this.handleReplaceResults);
                window.removeEventListener('keydown', this.onKeyDown);
                document.body.style.overflow = '';
            }
        });

        Vue.component('file-item', {
            props: ['item', 'current'],
            template: `
                <li :class="{current: item == current, marked: item.marked}">
                    <div
                        class="tree-node"
                        :data-path="item.path"
                        :data-repository="item.repository"
                        :data-quick-access="item.quickAccess ? 1 : 0"
                        @click="onClickTreeNode"
                        @contextmenu.prevent.stop="onContextTreeNode"
                    >
                        <span class="tree-icon" v-if="item.isDir">
                            {{ item.expanded ? '▾' : '▸' }}
                        </span>
                        <span class="file-icon" v-else>
                            <svg xmlns="http://www.w3.org/2000/svg" height="16" viewBox="0 -960 960 960" width="16"><path d="M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.717-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.162 50.85Q725.676-96 695.96-96H263.717ZM528-624h168L528-792v168Z"/></svg>
                        </span>
                        <span>{{ item.name }}</span>
                    </div>
                    <ul v-show="item.expanded && item.children">
                        <file-item :item="child" :current="current" :key="child.id" v-for="child in sortedChildren" @item-clicked="$emit('item-clicked', $event)" @item-context="$emit('item-context', $event)"/>
                    </ul>
                </li>
            `,
            data() {
                return {
                    expanded: false
                };
            },
            computed: {
                sortedChildren() {
                    // Nếu là quick access thì không sort
                    if (this.item.path == 'quick-access://') {
                        return this.item.children;
                    }

                    // Sort children so that folders appear first, followed by files
                    return this.item.children.sort((a, b) => {
                        if (a.isDir && !b.isDir) return -1;
                        if (!a.isDir && b.isDir) return 1;

                        if (a.name < b.name) return -1;
                        if (a.name > b.name) return 1;

                        return 0;
                    });
                },
                hasChildren() {
                    return this.item.children && this.item.children.length
                }
            },
            methods: {
                toggleExpand() {
                    this.$set(this.item, 'expanded', !this.item.expanded);
                },
                onClickTreeNode() {
                    this.$emit('item-clicked', this.item)
                },
                onContextTreeNode(e) {
                    this.$emit('item-context', { item: this.item, event: e})
                }
            }
        });

        Vue.component('explorer-files-search', {
            props: {
                repository: {
                    type: String,
                    default: null
                },
                rootNode: {
                    type: Object,
                    default: null
                },
                maxResults: {
                    type: Number,
                    default: 50
                }
            },
            template: `
                <div class="explorer-files-search-root" ref="root">
                    <span
                        class="explorer-files-search-clear"
                        v-if="searchQuery"
                        @click="clearSearch"
                        title="Xóa"
                    >✖</span>

                    <input
                        class="explorer-files-search-input"
                        ref="searchInput"
                        v-model="searchQuery"
                        @input="onSearchInput($event)"
                        @focus="onInputFocus"
                        placeholder="Tìm file..."
                        autocomplete="off"
                    />

                    <div
                        class="explorer-files-search-overlay"
                        v-if="showOverlay"
                        ref="searchOverlay"
                        @mousedown.stop
                    >
                        <ul class="explorer-files-search-list" v-if="limitedResults.length">
                            <li
                                v-for="result in limitedResults"
                                :key="result.relpath"
                                class="explorer-files-search-item"
                                @click="onItemClick(result)"
                            >
                                <span
                                    v-html="result.relpath"
                                ></span>
                            </li>
                        </ul>
                    </div>
                </div>
            `,
            data() {
                return {
                    searchQuery: '',
                    searchResults: [],
                    isSearching: false,
                    showResults: false,
                    debounceTimer: null,
                    currentAbortController: null
                };
            },
            computed: {
                limitedResults() {
                    return this.searchResults.slice(0, this.maxResults);
                },
                showOverlay() {
                    return this.showResults;
                }
            },
            methods: {
                onInputFocus() {
                    if (this.searchQuery.trim()) {
                        if (this.searchResults.length > 0) {
                            this.showResults = true;
                        }
                    }
                },

                onSearchInput(event) {
                    this.clearDebounceTimer();
                    this.debounceTimer = setTimeout(() => {
                        // IMPORTANT: Sử dụng event.target.value thay vì dựa vào
                        // v-model để không bị vấn đề khi dùng bộ gõ tiếng Việt
                        // với iBus trên Linux
                        const query = event.target.value.trim();
                        this.performSearch(query);
                    }, 300);
                },

                async performSearch(query) {
                    if (!query || query.length < 3) {
                        return;
                    }

                    // Cancel request đang pending (nếu có)
                    this.cancelPendingRequest();

                    this.isSearching = true;

                    // Tạo AbortController mới cho request này
                    this.currentAbortController = new AbortController();

                    try {
                        const response = await axios.get('index2.php', {
                            params: {
                                ajax: 1,
                                verbose: 0,
                                action: 'search-files',
                                repository: this.rootNode ? this.rootNode.repository : this.repository,
                                path: this.rootNode ? this.rootNode.path : '',
                                filename: query
                            },
                            // Thêm signal để có thể cancel request
                            signal: this.currentAbortController.signal
                        });

                        if (response.data.success) {
                            this.searchResults = response.data.results || [];
                            if (this.searchResults.length > 0) {
                                this.showResults = true;
                            }
                        }
                        if (response.data.message) {
                            window.showMessage(response.data.message);
                        }
                    } catch (error) {
                        // Kiểm tra xem error có phải do request bị cancel không
                        if (error.name === 'AbortError' || error.code === 'ERR_CANCELED') {
                            return; // Không hiển thị error message cho cancelled request
                        }

                        console.error('Search failed:', error);
                        this.searchResults = [];
                    } finally {
                        this.isSearching = false;
                        // Clear controller reference sau khi request hoàn thành
                        this.currentAbortController = null;
                    }
                },

                cancelPendingRequest() {
                    if (this.currentAbortController) {
                        this.currentAbortController.abort();
                        this.currentAbortController = null;
                    }
                },

                clearDebounceTimer() {
                    if (this.debounceTimer) {
                        clearTimeout(this.debounceTimer);
                        this.debounceTimer = null;
                    }
                },

                onItemClick(item) {
                    this.$emit('item-clicked', item);
                    this.hideResults();
                },

                clearSearch() {
                    this.searchQuery = '';
                    this.hideResults();
                    this.clearDebounceTimer();
                    this.$nextTick(() => {
                        if (this.$refs.searchInput) {
                            this.$refs.searchInput.focus();
                        }
                    });
                },

                hideResults() {
                    this.showResults = false;
                    // Không clear searchResults để có thể hiển thị lại khi focus
                    this.isSearching = false;
                },

                onDocumentClick(event) {
                    const overlay = this.$refs.searchOverlay;
                    const input = this.$refs.searchInput;

                    if (!overlay) return;

                    if (overlay.contains(event.target) || (input && input.contains(event.target))) {
                        return;
                    }

                    this.hideResults();
                }
            },

            mounted() {
                document.addEventListener('mousedown', this.onDocumentClick);
            },

            beforeDestroy() {
                document.removeEventListener('mousedown', this.onDocumentClick);
                this.cancelPendingRequest();
                this.clearDebounceTimer();
            }
        });

        Vue.component('hugo-plugin', {
            props: ['context', 'data', 'open', 'expand'],
            template: `
                <span>
                    <span v-if="context=='toolbar'">
                        <span class="sep"></span>
                        <span @click="onClickNewHugoFile" title="Add new Hugo file">
                            <svg xmlns="http://www.w3.org/2000/svg" height="16" viewBox="0 -960 960 960" width="16"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm276-102q20-22 36-47.5t26.5-53q10.5-27.5 16-56.5t5.5-59q0-98-54.5-179T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q17 0 28.5 11.5T600-440v120h40q26 0 47 15.5t29 40.5Z"/></svg>
                        </span>
                    </span>
                    <span v-if="context=='contextmenu'">
                        <li class="sep"></li>
                        <li @click="onClickNewHugoFile">New Hugo file...</li>
                    </span>
                </span>
            `,
            methods: {
                titleToHugoFilename(title) {
                    // Chuyển đổi các ký tự tiếng Việt sang phiên bản latin
                    const convertVietnameseChars = str => {
                        const vietnameseChars   = ['á', 'à', 'ả', 'ã', 'ạ', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ', 'đ', 'é', 'è', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ', 'í', 'ì', 'ỉ', 'ĩ', 'ị', 'ó', 'ò', 'ỏ', 'õ', 'ọ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ', 'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ', 'ú', 'ù', 'ủ', 'ũ', 'ụ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự', 'ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ'];
                        const latinChars        = ['a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'd', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'y', 'y', 'y', 'y', 'y'];

                        for (let i = 0; i < vietnameseChars.length; i++) {
                            const regex = new RegExp(vietnameseChars[i], 'g');
                            str = str.replace(regex, latinChars[i]);
                        }

                        return str;
                    };

                    // Loại bỏ các ký tự không hợp lệ trong tên tập tin
                    const cleanTitle = convertVietnameseChars(title).replace(/[^\w\s]/gi, '');

                    // Thay thế dấu cách bằng dấu gạch ngang
                    const filename = cleanTitle.replace(/\s+/g, '-').toLowerCase();

                    // Thêm đuôi .md
                    return `${filename}.md`;
                },
                onClickNewHugoFile() {
                    let { repository, currentDirectoryNode } = this.data

                    jPrompt("Title: ", '', 'New Hugo content', (title) => {
                        if (title) {
                            let name = title.endsWith('.md')? title : this.titleToHugoFilename(title)

                            let newNode = { name: name, isFile: true, path: currentDirectoryNode.path + '/' + name, children: []}

                            currentDirectoryNode.children.push(newNode)

                            axios({
                                method: 'post',
                                url: 'index2.php?action=new-hugo-content&name=' + name + '&repository=' + repository + '&path=' + currentDirectoryNode.path + '&verbose=0&ajax=1'
                            }).then(({data}) => {
                                if (data.success) {
                                    this.open(newNode)
                                }
                                if (data.message) {
                                    window.showMessage(data.message)
                                }
                            }).catch((err) => {
                                console.log(err)
                            }).finally(() => {
                                this.expand(currentDirectoryNode)
                            })
                        }
                    })
                }
            }
        });

        new Vue({
            el: '#editor',
            data: {
                repository: '<?php echo isset($_SESSION['repository'])? $_SESSION['repository'] : ''; ?>',
                directoryStructure: [],
                nodeIndex: new Map(), // Index for fast node lookup
                contextNode: null,
                currentDirectoryNode: null,
                markedNodes: [],
                plugins: ['hugo-plugin'],
                terminalError: '',
                terminalFullMode: false
            },
            computed: {
                pluginData() {
                    return {
                        repository: this.repository,
                        currentDirectoryNode: this.currentDirectoryNode,
                        contextNode: this.contextNode
                    }
                },
                quickAccessNode() {
                    return this.directoryStructure[0]
                }
            },

            mounted() {
                this.ensureBasicDirectoryStructure();

                this.initTerminal()

                window.editorComponent = this;

                window.editorEventBus.$on('editor-tab-clicked', this.handleEditorTabClicked);
                window.editorEventBus.$on('pin-to-quick-access', this.handlePinToQuickAccess);
            },
            beforeDestroy() {
                if (window.editorComponent === this) {
                    window.editorComponent = null;
                }

                window.editorEventBus.$off('editor-tab-clicked', this.handleEditorTabClicked);
                window.editorEventBus.$off('pin-to-quick-access', this.handlePinToQuickAccess);
            },
            methods: {
                initWebSocket(term) {
                    const socket = new WebSocket("wss://cloudpad9.com/4423df30c2370b6c952d07397078b3ae")

                    socket.addEventListener("open", (event) => {
                        this.terminalError = ''

                        this.pingInterval && clearInterval(this.pingInterval);

                        this.pingInterval = setInterval(() => {
                            if (socket.readyState === WebSocket.OPEN && Date.now() - this.latestSocketTraffic > 30000) {
                                //socket.send('\x1b[C'); // Gởi ký tự tương ứng với right arrow
                                socket.send('\b'); // Gởi ký tự tương ứng với backspace
                            }
                        }, 30000);
                    });

                    if (this.attachAddon) {
                        this.attachAddon.dispose()
                    }

                    this.attachAddon = new AttachAddon.AttachAddon(socket)

                    term.loadAddon(this.attachAddon)

                    socket.addEventListener("error", (error) => {
                        this.terminalError = "Cannot connect to websocket wss://cloudpad9.com/4423df30c2370b6c952d07397078b3ae<br>- HINTS: run `su -c \"node [cloudpad9]/builder/bin/node/xterm-websocket-pty/backend.js &\" - cloudpad9`"
                    });

                    socket.addEventListener("message", (e) => {
                        this.latestSocketTraffic = Date.now();
                    });

                    socket.addEventListener("close", (event) => {
                        this.terminalError = "Websocket connection has been closed."

                        this.pingInterval && clearInterval(this.pingInterval);

                        setTimeout(() => {
                            this.terminalError = "Reconnecting..."

                            term.write("\33[2K\r")

                            this.initWebSocket(term)
                        }, 5000);
                    });
                },
                initTerminal() {
                    const el = document.getElementById('terminal')

                    if (!el) return;

                    const term = new Terminal({
                        cursorBlink: true,
                        theme: {
                            background: '#202020'
                        }
                    })

                    this.fitAddon = new FitAddon.FitAddon()

                    term.loadAddon(this.fitAddon)

                    term.open(el)

                    this.fitAddon.fit()

                    window.addEventListener('resize', () => {
                        this.fitAddon.fit()
                    });

                    this.initWebSocket(term)
                },
                toggleTerminalMode() {
                    this.terminalFullMode = !this.terminalFullMode

                    this.$nextTick(() => {
                        this.fitAddon.fit()
                    })
                },
                onChangeRepository() {
                    if (!this.repository) {
                        return
                    }

                    const repositoryNode = this.ensureRepositoryNode(this.repository);

                    this.currentDirectoryNode = repositoryNode;
                },

                handleEditorTabClicked({ repository, path }) {
                    this.expandPathToNode(repository, path);
                },

                handlePinToQuickAccess({ repository, path }) {
                    this.pinToQuickAccess(repository, path);
                },

                /**
                 * Đảm bảo có repository node tương ứng trong node 'Repositories'
                 */
                ensureRepositoryNode(repository) {
                    if (!repository || typeof repository !== 'string') {
                        console.warn('ensureRepositoryNode: Invalid repository parameter:', repository);
                        return null;
                    }

                    // Đảm bảo có directory structure cơ bản
                    this.ensureBasicDirectoryStructure();

                    // Tìm node 'Repositories'
                    let repositoriesNode = this.directoryStructure.find(node => node.name === 'Repositories');

                    // Kiểm tra xem repository node đã tồn tại chưa
                    let repositoryNode = repositoriesNode.children.find(child =>
                        child.repository === repository
                    );

                    if (!repositoryNode) {
                        // Tạo repository node mới
                        repositoryNode = {
                            name: repository,
                            path: '',
                            repository: repository,
                            isDir: true,
                            children: [],
                            expanded: false,
                            loaded: false
                        };

                        // Thêm vào children của Repositories node
                        repositoriesNode.children.push(repositoryNode);

                        // Đảm bảo Repositories node được expand để user thấy repository mới
                        repositoriesNode.expanded = true;
                    }

                    return repositoryNode;
                },

                ensureBasicDirectoryStructure() {
                    if (this.directoryStructure.length === 0) {
                        this.directoryStructure = [
                            {
                                name: 'Quick access',
                                path: 'quick-access://',
                                isDir: true,
                                children: [],
                                expanded: false
                            },
                            {
                                name: 'Repositories',
                                path: '',
                                repository: '',
                                isDir: true,
                                expanded: true,
                                children: []
                            }
                        ];
                    }
                },

                async expandDirectoryNode(node, { force = false } = {}) {
                    // Prevent duplicate requests
                    if (node.isExpanding) return;

                    // Đã load rồi thì chỉ expand, trừ khi force = true
                    if (!force && node.loaded === true && Array.isArray(node.children)) {
                        this.$set(node, 'expanded', true);
                        return;
                    }

                    if (!node.repository && !node.path && node.children.length) {
                        this.$set(node, 'expanded', true);
                        this.$set(node, 'loaded', true);
                        return;
                    }

                    try {
                        this.$set(node, 'isExpanding', true);

                        const response = await axios({
                            method: 'get',
                            url: 'index2.php?action=get-directory-children&repository=' + (node.repository || '') + '&path=' + node.path + '&verbose=0&ajax=1'
                        });

                        const data = response.data;

                        if (data.success) {
                            if (data.children && Array.isArray(data.children)) {
                                data.children.forEach(child => {
                                    child.parent = node;
                                });

                                this.indexNodes(data.children);
                            }

                            this.$set(node, 'children', data.children);
                            this.$set(node, 'expanded', true);
                            this.$set(node, 'loaded', true);
                        }
                    } catch (err) {
                        console.error('Error expanding directory:', err);
                    } finally {
                        this.$set(node, 'isExpanding', false);
                    }
                },

                ////////////////////////////////////////////////////////////////
                // expandToNode
                ////////////////////////////////////////////////////////////////
                parsePath(path) {
                    const protocolIndex = path.indexOf('://');

                    if (protocolIndex === -1) {
                        return path.split('/').filter(part => part.length > 0);
                    }

                    const protocol = path.substring(0, protocolIndex + 3);
                    const remainingPath = path.substring(protocolIndex + 3);
                    const pathParts = remainingPath.split('/').filter(part => part.length > 0);

                    return [protocol, ...pathParts];
                },

                // Build index for fast node lookup
                buildNodeIndex() {
                    this.nodeIndex.clear();
                    this.indexNodes(this.directoryStructure);
                },

                // Recursive function to index all nodes
                indexNodes(nodes) {
                    if (!Array.isArray(nodes)) return;

                    nodes.forEach(node => {
                        if (node.repository) {
                            const key = `${node.repository}:${node.path}`;
                            this.nodeIndex.set(key, node);
                        }

                        // Recursively index children
                        if (node.children && Array.isArray(node.children)) {
                            this.indexNodes(node.children);
                        }
                    });
                },

                // Fast node lookup using index
                findNodeByPath(repository, path) {
                    const key = `${repository}:${path}`;
                    return this.nodeIndex.get(key) || null;
                },

                scrollToNode(repository, path, opts = 'smooth') {
                    // opts: string (behavior) | { behavior?:'smooth'|'auto', prefer?:'repo'|'quick'|null }
                    const behavior = typeof opts === 'string' ? opts : (opts.behavior || 'smooth');
                    const prefer   = typeof opts === 'object' ? (opts.prefer || 'repo') : 'repo';

                    this.$nextTick(() => {
                        const selector = `[data-path="${path}"][data-repository="${repository}"]`;
                        const candidates = Array.from(document.querySelectorAll(selector));
                        if (!candidates.length) {
                            console.warn('Node element not found for scrolling:', repository, path);
                            return;
                        }
                        let nodeElement = null;
                        if (prefer === 'repo') {
                            nodeElement = candidates.find(el => el.getAttribute('data-quick-access') !== '1') || candidates[0];
                        } else if (prefer === 'quick') {
                            nodeElement = candidates.find(el => el.getAttribute('data-quick-access') === '1') || candidates[0];
                        } else {
                            nodeElement = candidates[0];
                        }

                        nodeElement.scrollIntoView({
                            behavior,
                            block: 'center',
                            inline: 'nearest'
                        });
                        this.highlightNode(nodeElement);

                        // --- cập nhật currentDirectoryNode ---
                        const node = this.findNodeByPath(repository, path);
                        if (node) {
                            // nếu là file → current = parent
                            // nếu là folder → current = chính nó
                            const dirNode = node.isFile ? node.parent : node;
                            if (dirNode) {
                                this.$set(this, 'currentDirectoryNode', dirNode);
                            }
                        }
                    });
                },

                highlightNode(element, duration = 2000) {
                    // Add highlight class
                    element.classList.add('scroll-highlight');

                    // // Remove highlight after duration
                    // setTimeout(() => {
                    //     element.classList.remove('scroll-highlight');
                    // }, duration);
                },

                async expandToNode(node) {
                    await this.expandPathToNode(node.repository, node.path);
                },

                // Nếu targetPath không bắt đầu bằng *:// (ví dụ: 1://) thì prepend `1://` vào
                normalizeTargetPath(targetPath) {
                    if (!targetPath) {
                        return targetPath;
                    }

                    // Regex để kiểm tra pattern *:// (một hoặc nhiều ký tự, theo sau là ://)
                    const protocolPattern = /^.+:\/\//;

                    if (!protocolPattern.test(targetPath)) {
                        // Nếu không có protocol, thêm "1://" vào đầu
                        return '1://' + targetPath;
                    }

                    return targetPath;
                },

                async expandPathToNode(repository, targetPath) {
                    const normalize = (p) => /^.+:\/\//.test(p) ? p : ('1://' + String(p || '').replace(/^\/+/, ''));
                    const target = normalize(targetPath);

                    // Build prefixes: '', '1://', '1://a', '1://a/b', ...
                    const prefixes = (() => {
                        const m = /^(.+):\/\/(.*)$/.exec(target);
                        const branch = m ? m[1] : '';
                        const rest   = m ? m[2] : '';
                        const parts  = rest.split('/').filter(Boolean);
                        // NOTE: Không đưa '' (repo root) vào prefixes để tránh break sớm vòng lặp.
                        const prefs  = [];
                        if (branch) prefs.push(`${branch}://`);
                        let acc = '';
                        for (const seg of parts) {
                            acc = acc ? `${acc}/${seg}` : seg;
                            prefs.push(`${branch}://${acc}`);
                        }
                        return prefs;
                    })();

                    const repositoryNode = this.ensureRepositoryNode(repository);
                    this.buildNodeIndex();

                    const existsNode = (path) => this.findNodeByPath(repository, path);
                    const isHydrated = (node) => !!(node && Array.isArray(node.children) && node.loaded === true);

                    // Tìm điểm bắt đầu cần fetch (startPath)
                    let startPath = null;
                    for (let i = 0; i < prefixes.length; i++) {
                        const pref = prefixes[i];
                        const node = existsNode(pref);
                        if (!node) {
                            // Nếu thiếu node ngay từ cấp đầu (branch root), fetch từ repo root ('')
                            startPath = i > 0 ? prefixes[i - 1] : '';
                            break;
                        }
                        const looksDir = pref.endsWith('://') || node.isDir === true;
                        if (looksDir && !isHydrated(node)) {
                            startPath = pref;
                            break;
                        }
                    }

                    // Không cần fetch — chỉ scroll
                    // NOTE: startPath có thể là '' (repo root) -> vẫn cần fetch
                    if (startPath === null) {
                        this.scrollToNode(repository, target, { prefer: 'repo' });
                        return;
                    }

                    try {
                        const { data } = await axios.get('index2.php', {
                            params: {
                                action: 'expand-path',
                                repository,
                                target,
                                start: startPath,
                                verbose: 0,
                                ajax: 1
                            }
                        });

                        if (!data || !data.success) {
                            window.showMessage && window.showMessage((data && data.message) || 'Cannot expand path.');
                            return;
                        }

                        const chain = Array.isArray(data.chain) ? data.chain : [];
                        let currentNode = startPath === '' ? repositoryNode : this.findNodeByPath(repository, startPath);

                        for (let i = 0; i < chain.length; i++) {
                            const step = chain[i]; // { path, children }
                            const kids = (step.children || []).map(child => ({ ...child, parent: currentNode }));
                            this.$set(currentNode, 'children', kids);
                            this.$set(currentNode, 'expanded', true);
                            this.$set(currentNode, 'loaded', true);
                            this.indexNodes(kids);

                            if (i + 1 < chain.length) {
                                const nextPath = chain[i + 1].path;
                                currentNode = this.findNodeByPath(repository, nextPath) || currentNode;
                            }
                        }

                        this.scrollToNode(repository, target, { prefer: 'repo' });
                    } catch (err) {
                        console.error(err);
                        window.showMessage && window.showMessage('Expand path failed.');
                    }
                },

                ////////////////////////////////////////////////////////////////
                // onItemClicked
                ////////////////////////////////////////////////////////////////

                onItemClicked(node) {
                    if (node.isFile) {
                        this.openNode(node)
                    } else {
                        this.currentDirectoryNode = node

                        if (node.expanded) {
                            this.$set(node, 'expanded', false)
                        } else {
                            this.expandDirectoryNode(node, { force: true })
                        }
                    }
                },
                onItemContext(payload) {
                    let node = payload.item

                    this.contextNode = node

                    if (node.isDir) {
                        this.currentDirectoryNode = node
                    }

                    this.$refs.contextmenu.show(event.clientX, event.clientY)
                },
                hideContextMenu() {
                    this.$refs.contextmenu.hide()
                },
                openNode(node) {
                    window.dev_editor.editorOpenFile(node.path, node.repository)
                },
                onClickNewFile() {
                    if (!this.currentDirectoryNode) {
                        return
                    }

                    jPrompt("File name: ", '', 'New file', (name) => {
                        if (name) {
                            let newNode = { name: name, isFile: true, path: this.currentDirectoryNode.path + '/' + name, repository: this.currentDirectoryNode.repository, children: []}

                            this.currentDirectoryNode.children.push(newNode)

                            axios({
                                method: 'post',
                                url: 'index2.php?action=new-file&name=' + name + '&repository=' + this.currentDirectoryNode.repository + '&path=' + this.currentDirectoryNode.path + '&verbose=0&ajax=1'
                            }).then(({data}) => {
                                if (data.success) {
                                    this.openNode(newNode)
                                }
                                if (data.message) {
                                    window.showMessage(data.message)
                                }
                            }).catch((err) => {
                                console.log(err)
                            }).finally(() => {
                                this.expandDirectoryNode(this.currentDirectoryNode)
                            })
                        }
                    })
                },
                onClickNewDirectory() {
                    if (!this.currentDirectoryNode) {
                        return
                    }

                    jPrompt("Directory name: ", '', 'New directory', (name) => {
                        if (name) {
                            this.currentDirectoryNode.children.push({ name: name, isDir: true, path: this.currentDirectoryNode.path + '/' + name, repository: this.currentDirectoryNode.repository, children: []})

                            axios({
                                method: 'post',
                                url: 'index2.php?action=new-directory&name=' + name + '&repository=' + this.currentDirectoryNode.repository + '&path=' + this.currentDirectoryNode.path + '&verbose=0&ajax=1'
                            }).then(({data}) => {
                                if (data.message) {
                                    window.showMessage(data.message)
                                }
                            }).catch((err) => {
                                console.log(err)
                            }).finally(() => {
                                this.expandDirectoryNode(this.currentDirectoryNode)
                            })
                        }
                    })
                },
                onClickUploadFiles() {
                    if (!this.currentDirectoryNode) {
                        return
                    }

                    this.$refs.files.click()
                },
                onFilesSelected(e) {
                    var files = e.target.files || e.dataTransfer.files;

                    if (!files) {
                        return
                    }

                    ([...files]).forEach(f => {
                        this.uploadFile(f)
                    })
                },
                uploadFile(file) {
                    let name = file.name

                    this.currentDirectoryNode.children.push({ name: name, isFile: true, path: this.currentDirectoryNode.path + '/' + name, repository: this.currentDirectoryNode.repository, children: []})

                    let formData = new FormData()

                    formData.append('file', file)

                    axios({
                        method: 'post',
                        url: 'index2.php?action=upload_file&name=' + name + '&repository=' + this.currentDirectoryNode.repository + '&path=' + this.currentDirectoryNode.path + '&verbose=0&ajax=1',
                        data: formData,
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }).then(({data}) => {
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                        this.expandDirectoryNode(this.currentDirectoryNode)
                    })
                },
                onClickDeleteFile(node) {
                    if (!node.isFile) {
                        window.showMessage("Not a file")

                        return
                    }
                    jPrompt('Are you sure you want to delete this file? If so, type YES to confirm.', '', 'Confirmation', (text) => {
                        if (text != 'YES') {
                            return
                        }

                        this.$set(node, 'name', 'deleting...')

                        axios({
                            method: 'post',
                            url: 'index2.php?action=delete-file' + '&path=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                        }).then(({data}) => {
                            if (data.message) {
                                window.showMessage(data.message)
                            }
                        }).catch((err) => {
                            console.log(err)
                        }).finally(() => {
                            this.expandDirectoryNode(this.currentDirectoryNode)
                        })
                    })
                },
                onClickDownloadFile(node) {
                    this.hideContextMenu()

                    this.downloadFile(node.repository, node.path)
                },
                downloadFile(repository, path) {
                    let url = 'index2.php?action=download-file' + '&path=' + path + '&repository=' + repository;

                    // Gửi yêu cầu GET để tải file
                    axios.get(url, {
                        responseType: 'blob' // Quan trọng: yêu cầu trả về dưới dạng Blob
                    })
                    .then(response => {
                        // Tạo URL tạm thời từ Blob
                        const blob = new Blob([response.data], { type: response.headers['content-type'] });

                        // Tạo một đối tượng URL cho file
                        const downloadUrl = URL.createObjectURL(blob);

                        // Lấy tên file từ node.path (trích xuất từ đường dẫn)
                        const fileName = path.split('/').pop();  // Lấy phần cuối của đường dẫn (tên file)

                        // Tạo một thẻ <a> tạm thời để tải file về
                        const a = document.createElement('a');
                        a.href = downloadUrl;
                        a.download = fileName;

                        // Thực thi click tự động để tải file
                        a.click();

                        // Giải phóng URL tạm thời sau khi tải xong
                        URL.revokeObjectURL(downloadUrl);
                    })
                    .catch(error => {
                        console.error('Download failed:', error);
                        // Xử lý lỗi nếu có
                    });
                },
                onClickDownloadDirectory(node) {
                    this.hideContextMenu()

                    this.downloadDirectory(node.repository, node.path)
                },
                downloadDirectory(repository, path) {
                    let url = 'index2.php?action=download-directory' + '&path=' + encodeURIComponent(path) + '&repository=' + encodeURIComponent(repository);

                    axios.get(url, {
                        responseType: 'blob'
                    })
                    .then(response => {
                        // Check nếu response là JSON error (server trả về error dạng JSON)
                        if (response.data.type === 'application/json') {
                            response.data.text().then(text => {
                                const error = JSON.parse(text);
                                console.error('Download failed:', error.message);
                                alert('Error: ' + error.message);
                            });
                            return;
                        }

                        // Lấy tên file từ Content-Disposition header
                        let fileName = 'download.zip';
                        const contentDisposition = response.headers['content-disposition'];
                        if (contentDisposition) {
                            const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(contentDisposition);
                            if (matches != null && matches[1]) {
                                fileName = matches[1].replace(/['"]/g, '');
                            }
                        } else {
                            // Fallback: dùng tên folder + .zip
                            fileName = path.split('/').pop() + '.zip';
                        }

                        const blob = new Blob([response.data], { type: 'application/zip' });
                        const downloadUrl = URL.createObjectURL(blob);

                        const a = document.createElement('a');
                        a.href = downloadUrl;
                        a.download = fileName;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);

                        URL.revokeObjectURL(downloadUrl);
                    })
                    .catch(error => {
                        console.error('Download failed:', error);
                        alert('Download failed: ' + (error.response?.data?.message || error.message));
                    });
                },
                onClickRenameNode(node) {
                    let name = node.name

                    jPrompt(node.isFile? "New filename: " : "New directory name: ", name, 'Rename ' + name, (newname) => {
                        if (newname) {
                            let oldpath = node.path
                            let newpath = node.path.replace(/\/[^\/]+$/, `/${newname}`)

                            this.$set(node, 'name', newname)
                            this.$set(node, 'path', newpath)

                            axios({
                                method: 'post',
                                url: 'index2.php?action=' + (node.isFile? 'rename-file' : 'rename-directory') + '&newname=' + newname + '&filename=' + oldpath + '&repository=' + node.repository + '&verbose=0&ajax=1'
                            }).then(({data}) => {
                                if (data.message) {
                                    window.showMessage(data.message)
                                }
                            }).catch((err) => {
                                console.log(err)
                            }).finally(() => {
                                this.expandDirectoryNode(this.currentDirectoryNode)
                            })
                        }
                    })
                },
                onClickCloneFile(node) {
                    let name = node.name

                    jPrompt("New filename: ", name, 'Copy ' + name, (newname) => {
                        if (newname) {
                            let newpath = node.path.replace(/\/[^\/]+$/, `/${newname}`)

                            this.currentDirectoryNode.children.push({ name: newname, isFile: true, path: newpath, children: []})

                            axios({
                                method: 'post',
                                url: 'index2.php?action=clone-file&newname=' + newname + '&filename=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                            }).then(({data}) => {
                                if (data.message) {
                                    window.showMessage(data.message)
                                }
                            }).catch((err) => {
                                console.log(err)
                            }).finally(() => {
                                this.expandDirectoryNode(this.currentDirectoryNode)
                            })
                        }
                    })
                },
                onClickCopyPathToClipboard(node) {
                    this.hideContextMenu()

                    axios({
                        method: 'get',
                        url: 'index2.php?action=get-full-file-path&path=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                    }).then(({data}) => {
                        if (data.success) {
                            copyTextToClipboard(data.path)
                        }
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                    })
                },
                onClickGitStatus(node) {
                    this.hideContextMenu()

                    window.editorEventBus.$emit('git-status', {
                        repository: node.repository,
                        path: node.path
                    });
                },
                onClickGitLog(node) {
                    this.hideContextMenu()

                    window.editorEventBus.$emit('git-log', {
                        repository: node.repository,
                        path: node.path
                    });
                },
                onClickOpenFileLocation(node) {
                    this.hideContextMenu()

                    this.expandPathToNode(node.repository, node.path)
                },
                onClickMarkToMoveOrCopy(node) {
                    this.hideContextMenu()

                    this.markedNodes.push(node)

                    this.$set(node, 'marked', true)
                },
                onClickUnmark(node) {
                    this.hideContextMenu()

                    this.markedNodes = this.markedNodes.filter(item => item != node)

                    this.$set(node, 'marked', false)
                },
                onClickMoveToDirectory(node) {
                    this.hideContextMenu()

                    jPrompt('Are you sure you want to move files to this folder? If so, type YES to confirm.', '', 'Confirmation', (text) => {
                        if (text != 'YES') {
                            return
                        }

                        let paths = this.markedNodes.map(node => node.path).join(',')

                        this.markedNodes.forEach(node => node.marked = false)

                        this.markedNodes = []

                        axios({
                            method: 'post',
                            url: 'index2.php?action=move-files' + '&paths=' + paths + '&to=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                        }).then(({data}) => {
                            if (data.message) {
                                window.showMessage(data.message)
                            }
                        }).catch((err) => {
                            console.log(err)
                        }).finally(() => {
                            this.expandDirectoryNode(node)
                        })
                    })
                },
                onClickCopyToDirectory(node) {
                    this.hideContextMenu()

                    let paths = this.markedNodes.map(node => node.path).join(',')

                    this.markedNodes.forEach(node => node.marked = false)

                    this.markedNodes = []

                    axios({
                        method: 'post',
                        url: 'index2.php?action=copy-files' + '&paths=' + paths + '&to=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                    }).then(({data}) => {
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                        this.expandDirectoryNode(node)
                    })
                },
                onClickPinToQuickAccess(node) {
                    this.hideContextMenu()

                    this.pinToQuickAccess(node.repository, node.path)
                },
                pinToQuickAccess(repository, path) {
                    axios({
                        method: 'post',
                        url: 'index2.php?action=pin-to-quick-access' + '&path=' + path + '&repository=' + repository
                    }).then(({data}) => {
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                        if (this.quickAccessNode) {
                            this.expandDirectoryNode(this.quickAccessNode, { force: true })
                        }
                    })
                },
                onClickUnpinFromQuickAccess(node) {
                    this.hideContextMenu()

                    axios({
                        method: 'post',
                        url: 'index2.php?action=unpin-from-quick-access' + '&name=' + node.name + '&path=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                    }).then(({data}) => {
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                        if (this.quickAccessNode) {
                            this.expandDirectoryNode(this.quickAccessNode, { force: true })
                        }
                    })
                }
            }
        })
    </script>
</div>
<?php endif; ?>
