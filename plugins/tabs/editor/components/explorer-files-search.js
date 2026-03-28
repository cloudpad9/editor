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

