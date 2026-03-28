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

