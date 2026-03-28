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

