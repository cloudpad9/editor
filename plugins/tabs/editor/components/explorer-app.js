        new Vue({
            el: '#editor',
            data: {
                repository: '<?php echo htmlspecialchars((string)session_get('repository', ''), ENT_QUOTES, 'UTF-8'); ?>',
                directoryStructure: [],
                nodeIndex: new Map(), // Index for fast node lookup
                contextNode: null,
                currentDirectoryNode: null,
                markedNodes: [],
                plugins: ['hugo-plugin'],
                terminalError: '',
                terminalFullMode: false
            },
            computed: {
                pluginData() {
                    return {
                        repository: this.repository,
                        currentDirectoryNode: this.currentDirectoryNode,
                        contextNode: this.contextNode
                    }
                },
                quickAccessNode() {
                    return this.directoryStructure[0]
                }
            },

            mounted() {
                this.ensureBasicDirectoryStructure();

                this.initTerminal()

                window.editorComponent = this;

                window.editorEventBus.$on('editor-tab-clicked', this.handleEditorTabClicked);
                window.editorEventBus.$on('pin-to-quick-access', this.handlePinToQuickAccess);
            },
            beforeDestroy() {
                if (window.editorComponent === this) {
                    window.editorComponent = null;
                }

                window.editorEventBus.$off('editor-tab-clicked', this.handleEditorTabClicked);
                window.editorEventBus.$off('pin-to-quick-access', this.handlePinToQuickAccess);
            },
            methods: {
                initWebSocket(term) {
                    const socket = new WebSocket("wss://cloudpad9.com/4423df30c2370b6c952d07397078b3ae")

                    socket.addEventListener("open", (event) => {
                        this.terminalError = ''

                        this.pingInterval && clearInterval(this.pingInterval);

                        this.pingInterval = setInterval(() => {
                            if (socket.readyState === WebSocket.OPEN && Date.now() - this.latestSocketTraffic > 30000) {
                                //socket.send('\x1b[C'); // Gởi ký tự tương ứng với right arrow
                                socket.send('\b'); // Gởi ký tự tương ứng với backspace
                            }
                        }, 30000);
                    });

                    if (this.attachAddon) {
                        this.attachAddon.dispose()
                    }

                    this.attachAddon = new AttachAddon.AttachAddon(socket)

                    term.loadAddon(this.attachAddon)

                    socket.addEventListener("error", (error) => {
                        this.terminalError = "Cannot connect to websocket wss://cloudpad9.com/4423df30c2370b6c952d07397078b3ae<br>- HINTS: run `su -c \"node [cloudpad9]/builder/bin/node/xterm-websocket-pty/backend.js &\" - cloudpad9`"
                    });

                    socket.addEventListener("message", (e) => {
                        this.latestSocketTraffic = Date.now();
                    });

                    socket.addEventListener("close", (event) => {
                        this.terminalError = "Websocket connection has been closed."

                        this.pingInterval && clearInterval(this.pingInterval);

                        setTimeout(() => {
                            this.terminalError = "Reconnecting..."

                            term.write("\33[2K\r")

                            this.initWebSocket(term)
                        }, 5000);
                    });
                },
                initTerminal() {
                    const el = document.getElementById('terminal')

                    if (!el) return;

                    const term = new Terminal({
                        cursorBlink: true,
                        theme: {
                            background: '#202020'
                        }
                    })

                    this.fitAddon = new FitAddon.FitAddon()

                    term.loadAddon(this.fitAddon)

                    term.open(el)

                    this.fitAddon.fit()

                    window.addEventListener('resize', () => {
                        this.fitAddon.fit()
                    });

                    this.initWebSocket(term)
                },
                toggleTerminalMode() {
                    this.terminalFullMode = !this.terminalFullMode

                    this.$nextTick(() => {
                        this.fitAddon.fit()
                    })
                },
                onChangeRepository() {
                    if (!this.repository) {
                        return
                    }

                    const repositoryNode = this.ensureRepositoryNode(this.repository);

                    this.currentDirectoryNode = repositoryNode;
                },

                handleEditorTabClicked({ repository, path }) {
                    this.expandPathToNode(repository, path);
                },

                handlePinToQuickAccess({ repository, path }) {
                    this.pinToQuickAccess(repository, path);
                },

                /**
                 * Đảm bảo có repository node tương ứng trong node 'Repositories'
                 */
                ensureRepositoryNode(repository) {
                    if (!repository || typeof repository !== 'string') {
                        console.warn('ensureRepositoryNode: Invalid repository parameter:', repository);
                        return null;
                    }

                    // Đảm bảo có directory structure cơ bản
                    this.ensureBasicDirectoryStructure();

                    // Tìm node 'Repositories'
                    let repositoriesNode = this.directoryStructure.find(node => node.name === 'Repositories');

                    // Kiểm tra xem repository node đã tồn tại chưa
                    let repositoryNode = repositoriesNode.children.find(child =>
                        child.repository === repository
                    );

                    if (!repositoryNode) {
                        // Tạo repository node mới
                        repositoryNode = {
                            name: repository,
                            path: '',
                            repository: repository,
                            isDir: true,
                            children: [],
                            expanded: false,
                            loaded: false
                        };

                        // Thêm vào children của Repositories node
                        repositoriesNode.children.push(repositoryNode);

                        // Đảm bảo Repositories node được expand để user thấy repository mới
                        repositoriesNode.expanded = true;
                    }

                    return repositoryNode;
                },

                ensureBasicDirectoryStructure() {
                    if (this.directoryStructure.length === 0) {
                        this.directoryStructure = [
                            {
                                name: 'Quick access',
                                path: 'quick-access://',
                                isDir: true,
                                children: [],
                                expanded: false
                            },
                            {
                                name: 'Repositories',
                                path: '',
                                repository: '',
                                isDir: true,
                                expanded: true,
                                children: []
                            }
                        ];
                    }
                },

                async expandDirectoryNode(node, { force = false } = {}) {
                    // Prevent duplicate requests
                    if (node.isExpanding) return;

                    // Đã load rồi thì chỉ expand, trừ khi force = true
                    if (!force && node.loaded === true && Array.isArray(node.children)) {
                        this.$set(node, 'expanded', true);
                        return;
                    }

                    if (!node.repository && !node.path && node.children.length) {
                        this.$set(node, 'expanded', true);
                        this.$set(node, 'loaded', true);
                        return;
                    }

                    try {
                        this.$set(node, 'isExpanding', true);

                        const response = await axios({
                            method: 'get',
                            url: 'index2.php?action=get-directory-children&repository=' + (node.repository || '') + '&path=' + node.path + '&verbose=0&ajax=1'
                        });

                        const data = response.data;

                        if (data.success) {
                            if (data.children && Array.isArray(data.children)) {
                                data.children.forEach(child => {
                                    child.parent = node;
                                });

                                this.indexNodes(data.children);
                            }

                            this.$set(node, 'children', data.children);
                            this.$set(node, 'expanded', true);
                            this.$set(node, 'loaded', true);
                        }
                    } catch (err) {
                        console.error('Error expanding directory:', err);
                    } finally {
                        this.$set(node, 'isExpanding', false);
                    }
                },

                ////////////////////////////////////////////////////////////////
                // expandToNode
                ////////////////////////////////////////////////////////////////
                parsePath(path) {
                    const protocolIndex = path.indexOf('://');

                    if (protocolIndex === -1) {
                        return path.split('/').filter(part => part.length > 0);
                    }

                    const protocol = path.substring(0, protocolIndex + 3);
                    const remainingPath = path.substring(protocolIndex + 3);
                    const pathParts = remainingPath.split('/').filter(part => part.length > 0);

                    return [protocol, ...pathParts];
                },

                // Build index for fast node lookup
                buildNodeIndex() {
                    this.nodeIndex.clear();
                    this.indexNodes(this.directoryStructure);
                },

                // Recursive function to index all nodes
                indexNodes(nodes) {
                    if (!Array.isArray(nodes)) return;

                    nodes.forEach(node => {
                        if (node.repository) {
                            const key = `${node.repository}:${node.path}`;
                            this.nodeIndex.set(key, node);
                        }

                        // Recursively index children
                        if (node.children && Array.isArray(node.children)) {
                            this.indexNodes(node.children);
                        }
                    });
                },

                // Fast node lookup using index
                findNodeByPath(repository, path) {
                    const key = `${repository}:${path}`;
                    return this.nodeIndex.get(key) || null;
                },

                scrollToNode(repository, path, opts = 'smooth') {
                    // opts: string (behavior) | { behavior?:'smooth'|'auto', prefer?:'repo'|'quick'|null }
                    const behavior = typeof opts === 'string' ? opts : (opts.behavior || 'smooth');
                    const prefer   = typeof opts === 'object' ? (opts.prefer || 'repo') : 'repo';

                    this.$nextTick(() => {
                        const selector = `[data-path="${path}"][data-repository="${repository}"]`;
                        const candidates = Array.from(document.querySelectorAll(selector));
                        if (!candidates.length) {
                            console.warn('Node element not found for scrolling:', repository, path);
                            return;
                        }
                        let nodeElement = null;
                        if (prefer === 'repo') {
                            nodeElement = candidates.find(el => el.getAttribute('data-quick-access') !== '1') || candidates[0];
                        } else if (prefer === 'quick') {
                            nodeElement = candidates.find(el => el.getAttribute('data-quick-access') === '1') || candidates[0];
                        } else {
                            nodeElement = candidates[0];
                        }

                        nodeElement.scrollIntoView({
                            behavior,
                            block: 'center',
                            inline: 'nearest'
                        });
                        this.highlightNode(nodeElement);

                        // --- cập nhật currentDirectoryNode ---
                        const node = this.findNodeByPath(repository, path);
                        if (node) {
                            // nếu là file → current = parent
                            // nếu là folder → current = chính nó
                            const dirNode = node.isFile ? node.parent : node;
                            if (dirNode) {
                                this.$set(this, 'currentDirectoryNode', dirNode);
                            }
                        }
                    });
                },

                highlightNode(element, duration = 2000) {
                    // Add highlight class
                    element.classList.add('scroll-highlight');

                    // // Remove highlight after duration
                    // setTimeout(() => {
                    //     element.classList.remove('scroll-highlight');
                    // }, duration);
                },

                async expandToNode(node) {
                    await this.expandPathToNode(node.repository, node.path);
                },

                // Nếu targetPath không bắt đầu bằng *:// (ví dụ: 1://) thì prepend `1://` vào
                normalizeTargetPath(targetPath) {
                    if (!targetPath) {
                        return targetPath;
                    }

                    // Regex để kiểm tra pattern *:// (một hoặc nhiều ký tự, theo sau là ://)
                    const protocolPattern = /^.+:\/\//;

                    if (!protocolPattern.test(targetPath)) {
                        // Nếu không có protocol, thêm "1://" vào đầu
                        return '1://' + targetPath;
                    }

                    return targetPath;
                },

                async expandPathToNode(repository, targetPath) {
                    const normalize = (p) => /^.+:\/\//.test(p) ? p : ('1://' + String(p || '').replace(/^\/+/, ''));
                    const target = normalize(targetPath);

                    // Build prefixes: '', '1://', '1://a', '1://a/b', ...
                    const prefixes = (() => {
                        const m = /^(.+):\/\/(.*)$/.exec(target);
                        const branch = m ? m[1] : '';
                        const rest   = m ? m[2] : '';
                        const parts  = rest.split('/').filter(Boolean);
                        // NOTE: Không đưa '' (repo root) vào prefixes để tránh break sớm vòng lặp.
                        const prefs  = [];
                        if (branch) prefs.push(`${branch}://`);
                        let acc = '';
                        for (const seg of parts) {
                            acc = acc ? `${acc}/${seg}` : seg;
                            prefs.push(`${branch}://${acc}`);
                        }
                        return prefs;
                    })();

                    const repositoryNode = this.ensureRepositoryNode(repository);
                    this.buildNodeIndex();

                    const existsNode = (path) => this.findNodeByPath(repository, path);
                    const isHydrated = (node) => !!(node && Array.isArray(node.children) && node.loaded === true);

                    // Tìm điểm bắt đầu cần fetch (startPath)
                    let startPath = null;
                    for (let i = 0; i < prefixes.length; i++) {
                        const pref = prefixes[i];
                        const node = existsNode(pref);
                        if (!node) {
                            // Nếu thiếu node ngay từ cấp đầu (branch root), fetch từ repo root ('')
                            startPath = i > 0 ? prefixes[i - 1] : '';
                            break;
                        }
                        const looksDir = pref.endsWith('://') || node.isDir === true;
                        if (looksDir && !isHydrated(node)) {
                            startPath = pref;
                            break;
                        }
                    }

                    // Không cần fetch — chỉ scroll
                    // NOTE: startPath có thể là '' (repo root) -> vẫn cần fetch
                    if (startPath === null) {
                        this.scrollToNode(repository, target, { prefer: 'repo' });
                        return;
                    }

                    try {
                        const { data } = await axios.get('index2.php', {
                            params: {
                                action: 'expand-path',
                                repository,
                                target,
                                start: startPath,
                                verbose: 0,
                                ajax: 1
                            }
                        });

                        if (!data || !data.success) {
                            window.showMessage && window.showMessage((data && data.message) || 'Cannot expand path.');
                            return;
                        }

                        const chain = Array.isArray(data.chain) ? data.chain : [];
                        let currentNode = startPath === '' ? repositoryNode : this.findNodeByPath(repository, startPath);

                        for (let i = 0; i < chain.length; i++) {
                            const step = chain[i]; // { path, children }
                            const kids = (step.children || []).map(child => ({ ...child, parent: currentNode }));
                            this.$set(currentNode, 'children', kids);
                            this.$set(currentNode, 'expanded', true);
                            this.$set(currentNode, 'loaded', true);
                            this.indexNodes(kids);

                            if (i + 1 < chain.length) {
                                const nextPath = chain[i + 1].path;
                                currentNode = this.findNodeByPath(repository, nextPath) || currentNode;
                            }
                        }

                        this.scrollToNode(repository, target, { prefer: 'repo' });
                    } catch (err) {
                        console.error(err);
                        window.showMessage && window.showMessage('Expand path failed.');
                    }
                },

                ////////////////////////////////////////////////////////////////
                // onItemClicked
                ////////////////////////////////////////////////////////////////

                onItemClicked(node) {
                    if (node.isFile) {
                        this.openNode(node)
                    } else {
                        this.currentDirectoryNode = node

                        if (node.expanded) {
                            this.$set(node, 'expanded', false)
                        } else {
                            this.expandDirectoryNode(node, { force: true })
                        }
                    }
                },
                onItemContext(payload) {
                    let node = payload.item

                    this.contextNode = node

                    if (node.isDir) {
                        this.currentDirectoryNode = node
                    }

                    this.$refs.contextmenu.show(event.clientX, event.clientY)
                },
                hideContextMenu() {
                    this.$refs.contextmenu.hide()
                },
                openNode(node) {
                    window.dev_editor.editorOpenFile(node.path, node.repository)
                },
                onClickNewFile() {
                    if (!this.currentDirectoryNode) {
                        return
                    }

                    jPrompt("File name: ", '', 'New file', (name) => {
                        if (name) {
                            let newNode = { name: name, isFile: true, path: this.currentDirectoryNode.path + '/' + name, repository: this.currentDirectoryNode.repository, children: []}

                            this.currentDirectoryNode.children.push(newNode)

                            axios({
                                method: 'post',
                                url: 'index2.php?action=new-file&name=' + name + '&repository=' + this.currentDirectoryNode.repository + '&path=' + this.currentDirectoryNode.path + '&verbose=0&ajax=1'
                            }).then(({data}) => {
                                if (data.success) {
                                    this.openNode(newNode)
                                }
                                if (data.message) {
                                    window.showMessage(data.message)
                                }
                            }).catch((err) => {
                                console.log(err)
                            }).finally(() => {
                                this.expandDirectoryNode(this.currentDirectoryNode)
                            })
                        }
                    })
                },
                onClickNewDirectory() {
                    if (!this.currentDirectoryNode) {
                        return
                    }

                    jPrompt("Directory name: ", '', 'New directory', (name) => {
                        if (name) {
                            this.currentDirectoryNode.children.push({ name: name, isDir: true, path: this.currentDirectoryNode.path + '/' + name, repository: this.currentDirectoryNode.repository, children: []})

                            axios({
                                method: 'post',
                                url: 'index2.php?action=new-directory&name=' + name + '&repository=' + this.currentDirectoryNode.repository + '&path=' + this.currentDirectoryNode.path + '&verbose=0&ajax=1'
                            }).then(({data}) => {
                                if (data.message) {
                                    window.showMessage(data.message)
                                }
                            }).catch((err) => {
                                console.log(err)
                            }).finally(() => {
                                this.expandDirectoryNode(this.currentDirectoryNode)
                            })
                        }
                    })
                },
                onClickUploadFiles() {
                    if (!this.currentDirectoryNode) {
                        return
                    }

                    this.$refs.files.click()
                },
                onFilesSelected(e) {
                    var files = e.target.files || e.dataTransfer.files;

                    if (!files) {
                        return
                    }

                    ([...files]).forEach(f => {
                        this.uploadFile(f)
                    })
                },
                uploadFile(file) {
                    let name = file.name

                    this.currentDirectoryNode.children.push({ name: name, isFile: true, path: this.currentDirectoryNode.path + '/' + name, repository: this.currentDirectoryNode.repository, children: []})

                    let formData = new FormData()

                    formData.append('file', file)

                    axios({
                        method: 'post',
                        url: 'index2.php?action=upload_file&name=' + name + '&repository=' + this.currentDirectoryNode.repository + '&path=' + this.currentDirectoryNode.path + '&verbose=0&ajax=1',
                        data: formData,
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }).then(({data}) => {
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                        this.expandDirectoryNode(this.currentDirectoryNode)
                    })
                },
                onClickDeleteFile(node) {
                    if (!node.isFile) {
                        window.showMessage("Not a file")

                        return
                    }
                    jPrompt('Are you sure you want to delete this file? If so, type YES to confirm.', '', 'Confirmation', (text) => {
                        if (text != 'YES') {
                            return
                        }

                        this.$set(node, 'name', 'deleting...')

                        axios({
                            method: 'post',
                            url: 'index2.php?action=delete-file' + '&path=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                        }).then(({data}) => {
                            if (data.message) {
                                window.showMessage(data.message)
                            }
                        }).catch((err) => {
                            console.log(err)
                        }).finally(() => {
                            this.expandDirectoryNode(this.currentDirectoryNode)
                        })
                    })
                },
                onClickDownloadFile(node) {
                    this.hideContextMenu()

                    this.downloadFile(node.repository, node.path)
                },
                downloadFile(repository, path) {
                    let url = 'index2.php?action=download-file' + '&path=' + path + '&repository=' + repository;

                    // Gửi yêu cầu GET để tải file
                    axios.get(url, {
                        responseType: 'blob' // Quan trọng: yêu cầu trả về dưới dạng Blob
                    })
                    .then(response => {
                        // Tạo URL tạm thời từ Blob
                        const blob = new Blob([response.data], { type: response.headers['content-type'] });

                        // Tạo một đối tượng URL cho file
                        const downloadUrl = URL.createObjectURL(blob);

                        // Lấy tên file từ node.path (trích xuất từ đường dẫn)
                        const fileName = path.split('/').pop();  // Lấy phần cuối của đường dẫn (tên file)

                        // Tạo một thẻ <a> tạm thời để tải file về
                        const a = document.createElement('a');
                        a.href = downloadUrl;
                        a.download = fileName;

                        // Thực thi click tự động để tải file
                        a.click();

                        // Giải phóng URL tạm thời sau khi tải xong
                        URL.revokeObjectURL(downloadUrl);
                    })
                    .catch(error => {
                        console.error('Download failed:', error);
                        // Xử lý lỗi nếu có
                    });
                },
                onClickDownloadDirectory(node) {
                    this.hideContextMenu()

                    this.downloadDirectory(node.repository, node.path)
                },
                downloadDirectory(repository, path) {
                    let url = 'index2.php?action=download-directory' + '&path=' + encodeURIComponent(path) + '&repository=' + encodeURIComponent(repository);

                    axios.get(url, {
                        responseType: 'blob'
                    })
                    .then(response => {
                        // Check nếu response là JSON error (server trả về error dạng JSON)
                        if (response.data.type === 'application/json') {
                            response.data.text().then(text => {
                                const error = JSON.parse(text);
                                console.error('Download failed:', error.message);
                                alert('Error: ' + error.message);
                            });
                            return;
                        }

                        // Lấy tên file từ Content-Disposition header
                        let fileName = 'download.zip';
                        const contentDisposition = response.headers['content-disposition'];
                        if (contentDisposition) {
                            const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(contentDisposition);
                            if (matches != null && matches[1]) {
                                fileName = matches[1].replace(/['"]/g, '');
                            }
                        } else {
                            // Fallback: dùng tên folder + .zip
                            fileName = path.split('/').pop() + '.zip';
                        }

                        const blob = new Blob([response.data], { type: 'application/zip' });
                        const downloadUrl = URL.createObjectURL(blob);

                        const a = document.createElement('a');
                        a.href = downloadUrl;
                        a.download = fileName;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);

                        URL.revokeObjectURL(downloadUrl);
                    })
                    .catch(error => {
                        console.error('Download failed:', error);
                        alert('Download failed: ' + (error.response?.data?.message || error.message));
                    });
                },
                onClickRenameNode(node) {
                    let name = node.name

                    jPrompt(node.isFile? "New filename: " : "New directory name: ", name, 'Rename ' + name, (newname) => {
                        if (newname) {
                            let oldpath = node.path
                            let newpath = node.path.replace(/\/[^\/]+$/, `/${newname}`)

                            this.$set(node, 'name', newname)
                            this.$set(node, 'path', newpath)

                            axios({
                                method: 'post',
                                url: 'index2.php?action=' + (node.isFile? 'rename-file' : 'rename-directory') + '&newname=' + newname + '&filename=' + oldpath + '&repository=' + node.repository + '&verbose=0&ajax=1'
                            }).then(({data}) => {
                                if (data.message) {
                                    window.showMessage(data.message)
                                }
                            }).catch((err) => {
                                console.log(err)
                            }).finally(() => {
                                this.expandDirectoryNode(this.currentDirectoryNode)
                            })
                        }
                    })
                },
                onClickCloneFile(node) {
                    let name = node.name

                    jPrompt("New filename: ", name, 'Copy ' + name, (newname) => {
                        if (newname) {
                            let newpath = node.path.replace(/\/[^\/]+$/, `/${newname}`)

                            this.currentDirectoryNode.children.push({ name: newname, isFile: true, path: newpath, children: []})

                            axios({
                                method: 'post',
                                url: 'index2.php?action=clone-file&newname=' + newname + '&filename=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                            }).then(({data}) => {
                                if (data.message) {
                                    window.showMessage(data.message)
                                }
                            }).catch((err) => {
                                console.log(err)
                            }).finally(() => {
                                this.expandDirectoryNode(this.currentDirectoryNode)
                            })
                        }
                    })
                },
                onClickCopyPathToClipboard(node) {
                    this.hideContextMenu()

                    axios({
                        method: 'get',
                        url: 'index2.php?action=get-full-file-path&path=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                    }).then(({data}) => {
                        if (data.success) {
                            copyTextToClipboard(data.path)
                        }
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                    })
                },
                onClickGitStatus(node) {
                    this.hideContextMenu()

                    window.editorEventBus.$emit('git-status', {
                        repository: node.repository,
                        path: node.path
                    });
                },
                onClickGitLog(node) {
                    this.hideContextMenu()

                    window.editorEventBus.$emit('git-log', {
                        repository: node.repository,
                        path: node.path
                    });
                },
                onClickOpenFileLocation(node) {
                    this.hideContextMenu()

                    this.expandPathToNode(node.repository, node.path)
                },
                onClickMarkToMoveOrCopy(node) {
                    this.hideContextMenu()

                    this.markedNodes.push(node)

                    this.$set(node, 'marked', true)
                },
                onClickUnmark(node) {
                    this.hideContextMenu()

                    this.markedNodes = this.markedNodes.filter(item => item != node)

                    this.$set(node, 'marked', false)
                },
                onClickMoveToDirectory(node) {
                    this.hideContextMenu()

                    jPrompt('Are you sure you want to move files to this folder? If so, type YES to confirm.', '', 'Confirmation', (text) => {
                        if (text != 'YES') {
                            return
                        }

                        let paths = this.markedNodes.map(node => node.path).join(',')

                        this.markedNodes.forEach(node => node.marked = false)

                        this.markedNodes = []

                        axios({
                            method: 'post',
                            url: 'index2.php?action=move-files' + '&paths=' + paths + '&to=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                        }).then(({data}) => {
                            if (data.message) {
                                window.showMessage(data.message)
                            }
                        }).catch((err) => {
                            console.log(err)
                        }).finally(() => {
                            this.expandDirectoryNode(node)
                        })
                    })
                },
                onClickCopyToDirectory(node) {
                    this.hideContextMenu()

                    let paths = this.markedNodes.map(node => node.path).join(',')

                    this.markedNodes.forEach(node => node.marked = false)

                    this.markedNodes = []

                    axios({
                        method: 'post',
                        url: 'index2.php?action=copy-files' + '&paths=' + paths + '&to=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                    }).then(({data}) => {
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                        this.expandDirectoryNode(node)
                    })
                },
                onClickPinToQuickAccess(node) {
                    this.hideContextMenu()

                    this.pinToQuickAccess(node.repository, node.path)
                },
                pinToQuickAccess(repository, path) {
                    axios({
                        method: 'post',
                        url: 'index2.php?action=pin-to-quick-access' + '&path=' + path + '&repository=' + repository
                    }).then(({data}) => {
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                        if (this.quickAccessNode) {
                            this.expandDirectoryNode(this.quickAccessNode, { force: true })
                        }
                    })
                },
                onClickUnpinFromQuickAccess(node) {
                    this.hideContextMenu()

                    axios({
                        method: 'post',
                        url: 'index2.php?action=unpin-from-quick-access' + '&name=' + node.name + '&path=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                    }).then(({data}) => {
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                        if (this.quickAccessNode) {
                            this.expandDirectoryNode(this.quickAccessNode, { force: true })
                        }
                    })
                }
            }
        })
    </script>
