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

