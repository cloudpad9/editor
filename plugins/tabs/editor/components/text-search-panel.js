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

