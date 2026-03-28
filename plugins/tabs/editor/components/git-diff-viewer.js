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

