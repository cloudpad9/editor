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

