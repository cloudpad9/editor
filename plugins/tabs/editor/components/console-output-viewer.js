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

