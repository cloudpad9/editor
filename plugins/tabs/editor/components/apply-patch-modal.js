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

