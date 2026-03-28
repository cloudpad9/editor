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

