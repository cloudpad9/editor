<?php if (Builder::hasPermission('editor')) : ?>
<div id="editor">
    <div class="editor-file-bar commandbar">
        <form data-success="setEditorContent" data-verbose="0" action="index.php" method="POST" enctype="multipart/form-data" style="float:left;">
            <input type="hidden" name="action" value="open-file-by-name"/>
            <div class="moz-select-wrapper">
                <select v-model="repository" name="repository" class="repositories js-repository" @change="onChangeRepository">
                    <?php $repositories = $builder->getRepositories(); ?>

                    <?php foreach ($repositories as $code => $settings) : ?>
                        <option value="<?php echo $code; ?>" <?php echo isset($_SESSION['repository']) && $_SESSION['repository'] == $code? 'selected' : ''; ?>><?php echo $settings['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="text" class="filename js-filename" name="filename" value="" placeholder="Enter a filename to open"/>
            <!--<input type="submit" value="Open"/>-->
        </form>
        <form class="tmp-hidden" action="index.php" method="POST" enctype="multipart/form-data" style="float: left;">
            <input type="hidden" name="action" value="editor-close-all"/>
            <input type="submit" onclick="dev_editor.editorCloseAllFiles();return false;" value="Close all"/>
        </form>
        <form class="tmp-hidden" action="index.php" method="POST" enctype="multipart/form-data" style="float: left;">
            <input type="hidden" name="action" value="editor-clear-recents"/>
            <input type="submit" value="Clear recents"/>
        </form>
        <button onclick="dev_editor.editorNewFile()" style="float: left;">New</button>
        <button onclick="dev_editor.editorCloneFile()" style="float: left;">Clone</button>
        <button onclick="dev_editor.editorRecoverFile()" style="float: left;">Recover</button>
        <button onclick="dev_editor.editorRenameFile()" style="float: left;">Rename</button>
        <button onclick="dev_editor.editorRevertFile()" style="float: left;">Revert</button>
        <button onclick="dev_editor.editorReloadFile()" style="float: left;">Reload</button>
        <button onclick="dev_editor.editorBeautifyFile()" style="float: left;">Beautify</button>
        <button class="tmp-hidden" onclick="dev_editor.editorSetCurrentTabColor()" style="float: left;">Color</button>
        <button onclick="dev_editor.editorSyncFile()" style="float: left;">Sync</button>
        <button onclick="dev_editor.editorRevertSyncFile()" style="float: left;">Revert sync</button>
        <form action="index.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="rebuild-filepaths-indexes"/>
            <input type="hidden" name="repository" class="js-mirror-repository" value=""/>
            <input type="submit" onclick="$('.js-mirror-repository').val($('.js-repository').val())" value="Rebuild indexes"/>
        </form>
        <div style="clear:both"></div>
    </div>

    <div id="dev_editor" class="editor-tabs">
        <ul class="js-tabs-nav">

        </ul>
        <div style="display:flex;flex-direction: column;">
            <div class="pane" style="display:flex;height: calc(100vh - 196px)">
                <div class="js-tabs-container" style="flex:1;">

                </div>
                <div id="dev_editor_sidebar">
                    <div id="explorer">
                        <div class="toolbar" v-if="directoryStructure.length">
                            <input type="file" multiple ref="files" style="display: none" @change="onFilesSelected">
                            <span @click="onClickNewFile" title="Add new file">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20"><path d="M216-144q-29.7 0-50.85-21.15Q144-186.3 144-216v-528q0-29.7 21.15-50.85Q186.3-816 216-816h312v72H216v528h528v-312h72v312q0 29.7-21.15 50.85Q773.7-144 744-144H216Zm120-144v-72h288v72H336Zm0-108v-72h288v72H336Zm0-108v-72h288v72H336Zm336-96v-72h-72v-72h72v-72h72v72h72v72h-72v72h-72Z"/></svg>
                            </span>
                            <span @click="onClickNewDirectory" title="Add new folder">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20"><path d="M576-324h72v-72h72v-72h-72v-72h-72v72h-72v72h72v72ZM168-192q-29 0-50.5-21.5T96-264v-432q0-29.7 21.5-50.85Q139-768 168-768h216l96 96h312q29.7 0 50.85 21.15Q864-629.7 864-600v336q0 29-21.15 50.5T792-192H168Zm0-72h624v-336H450l-96-96H168v432Zm0 0v-432 432Z"/></svg>
                            </span>
                            <span @click="onClickUploadFiles" title="Upload files">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20"><path d="M444-336v-342L339-573l-51-51 192-192 192 192-51 51-105-105v342h-72ZM263.717-192Q234-192 213-213.15T192-264v-72h72v72h432v-72h72v72q0 29.7-21.162 50.85Q725.676-192 695.96-192H263.717Z"/></svg>
                            </span>
                            <component v-for="plugin in plugins" :is="plugin" context="toolbar" :data="pluginData" :open="openNode" :expand="expandDirectoryNode"></component>
                        </div>
                        <ul class="file-tree">
                            <template v-if="directoryStructure.length">
                                <file-item :item="item" :current="currentDirectoryNode" :key="item.id" v-for="item in directoryStructure" @item-clicked="onItemClicked" @item-context="onItemContext"/>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="pane terminal-wrapper" :class="{full: terminalFullMode}" @dblclick="toggleTerminalMode">
                <div class="gutter gutter-h"></div>
                <div style="position: relative">
                    <div id="terminal"></div>
                    <div class="terminal-error" v-if="terminalError" v-html="terminalError"></div>
                </div>
            </div>
        </div>
        <context-menu ref="contextmenu">
            <ul v-if="contextNode">
                <template v-if="contextNode.isDir">
                    <li @click="onClickNewFile">New file...</li>
                    <li @click="onClickNewDirectory">New folder...</li>
                    <li class="sep"></li>
                    <li @click="onClickRenameNode(contextNode)">Rename...</li>
                    <li class="sep"></li>
                    <li @click="onClickCopyPathToClipboard(contextNode)">Copy path to clipboard</li>
                    <li class="sep"></li>
                    <li v-if="!contextNode.quickAccess" @click="onClickPinToQuickAccess(contextNode)">Pin to Quick access</li>
                    <li v-else @click="onClickUnpinFromQuickAccess(contextNode)">Unpin from Quick access</li>
                    <li v-if="markedNodes.length" class="sep"></li>
                    <li v-if="markedNodes.length" @click="onClickMoveToDirectory(contextNode)">Move here</li>
                    <li v-if="markedNodes.length" class="sep"></li>
                    <li v-if="markedNodes.length" @click="onClickCopyToDirectory(contextNode)">Paste</li>
                </template>
                <template v-else>
                    <li @click="onClickDeleteFile(contextNode)">Delete...</li>
                    <li class="sep"></li>
                    <li @click="onClickRenameNode(contextNode)">Rename...</li>
                    <li class="sep"></li>
                    <li @click="onClickCloneFile(contextNode)">Clone...</li>
                    <li class="sep"></li>
                    <li @click="onClickCopyPathToClipboard(contextNode)">Copy path to clipboard</li>
                    <li class="sep"></li>
                    <li v-if="!contextNode.marked" @click="onClickMarkToMoveOrCopy(contextNode)">Mark to move</li>
                    <li class="sep"></li>
                    <li v-if="!contextNode.marked" @click="onClickMarkToMoveOrCopy(contextNode)">Copy</li>
                    <li v-else @click="onClickUnmark(contextNode)">Unmark</li>
                </template>
                <component v-for="plugin in plugins" :is="plugin" context="contextmenu" :data="pluginData" :open="openNode" :expand="expandDirectoryNode"></component>
            </ul>
        </context-menu>
    </div>

    <script type="text/javascript">
        $(function() {
            window.dev_editor = new Editor({
                el: '#dev_editor',
                files: <?php echo json_encode($builder->getEditorOpenFiles()); ?>,
                style: 2
            })

            initSplitPanes(document.getElementById('dev_editor'), {
                minHeight: 30
            })
        })
    </script>

    <script>
        Vue.component('context-menu', {
            props: [],
            template: `
                <div id="contextmenu" ref="contextmenu" v-if="active" :style="{ left: x, top: y}">
                    <slot></slot>
                </div>
            `,
            data() {
                return {
                    active: false,
                    x: 0,
                    y: 0
                };
            },
            mounted() {
                document.addEventListener('click', this.documentClick);
            },
            beforeDestroy() {
                document.removeEventListener('click', this.documentClick);
            },
            methods: {
                show: function(x, y, bottomUp) {
                    this.active = true

                    this.$nextTick(() => {
                        let largestHeight = window.innerHeight - this.$refs.contextmenu.offsetHeight - (!bottomUp? 25 : 0)
                        let largestWidth = window.innerWidth - this.$refs.contextmenu.offsetWidth - (!bottomUp? 25 : 0)

                        let top = !bottomUp? y : y - this.$refs.contextmenu.offsetHeight
                        let left = x

                        if (top > largestHeight) {
                            top = largestHeight
                        }

                        if (left > largestWidth) {
                            left = largestWidth
                        }

                        this.y = top + 'px'
                        this.x = left + 'px'
                    })
                },
                hide: function(e) {
                    this.active = false
                },
                documentClick: function(e) {
                    if (this.$refs.contextmenu && !this.$refs.contextmenu.contains(e.target)) {
                        this.hide()
                    }
                }
            }
        });

        Vue.component('file-item', {
            props: ['item', 'current'],
            template: `
                <li :class="{current: item == current, marked: item.marked}">
                    <div class="tree-node" @click="onClickTreeNode" @contextmenu.prevent.stop="onContextTreeNode">
                        <span class="tree-icon" v-if="item.isDir">
                            {{ item.expanded ? '▾' : '▸' }}
                        </span>
                        <span class="file-icon" v-else>
                            <svg xmlns="http://www.w3.org/2000/svg" height="16" viewBox="0 -960 960 960" width="16"><path d="M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.717-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.162 50.85Q725.676-96 695.96-96H263.717ZM528-624h168L528-792v168Z"/></svg>
                        </span>
                        <span>{{ item.name }}</span>
                    </div>
                    <ul v-show="item.expanded && item.children">
                        <file-item :item="child" :current="current" :key="child.id" v-for="child in sortedChildren" @item-clicked="$emit('item-clicked', $event)" @item-context="$emit('item-context', $event)"/>
                    </ul>
                </li>
            `,
            data() {
                return {
                    expanded: false
                };
            },
            computed: {
                sortedChildren() {
                    // Sort children so that folders appear first, followed by files
                    return this.item.children.sort((a, b) => {
                        if (a.isDir && !b.isDir) return -1;
                        if (!a.isDir && b.isDir) return 1;

                        if (a.name < b.name) return -1;
                        if (a.name > b.name) return 1;

                        return 0;
                    });
                },
                hasChildren() {
                    return this.item.children && this.item.children.length
                }
            },
            methods: {
                toggleExpand() {
                    this.$set(this.item, 'expanded', !this.item.expanded);
                },
                onClickTreeNode() {
                    this.$emit('item-clicked', this.item)
                },
                onContextTreeNode(e) {
                    this.$emit('item-context', { item: this.item, event: e})
                }
            }
        });

        Vue.component('hugo-plugin', {
            props: ['context', 'data', 'open', 'expand'],
            template: `
                <span>
                    <span v-if="context=='toolbar'">
                        <span class="sep"></span>
                        <span @click="onClickNewHugoFile" title="Add new Hugo file">
                            <svg xmlns="http://www.w3.org/2000/svg" height="16" viewBox="0 -960 960 960" width="16"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm276-102q20-22 36-47.5t26.5-53q10.5-27.5 16-56.5t5.5-59q0-98-54.5-179T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q17 0 28.5 11.5T600-440v120h40q26 0 47 15.5t29 40.5Z"/></svg>
                        </span>
                    </span>
                    <span v-if="context=='contextmenu'">
                        <li class="sep"></li>
                        <li @click="onClickNewHugoFile">New Hugo file...</li>
                    </span>
                </span>
            `,
            methods: {
                titleToHugoFilename(title) {
                    // Chuyển đổi các ký tự tiếng Việt sang phiên bản latin
                    const convertVietnameseChars = str => {
                        const vietnameseChars   = ['á', 'à', 'ả', 'ã', 'ạ', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ', 'đ', 'é', 'è', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ', 'í', 'ì', 'ỉ', 'ĩ', 'ị', 'ó', 'ò', 'ỏ', 'õ', 'ọ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ', 'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ', 'ú', 'ù', 'ủ', 'ũ', 'ụ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự', 'ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ'];
                        const latinChars        = ['a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'd', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'y', 'y', 'y', 'y', 'y'];

                        for (let i = 0; i < vietnameseChars.length; i++) {
                            const regex = new RegExp(vietnameseChars[i], 'g');
                            str = str.replace(regex, latinChars[i]);
                        }

                        return str;
                    };

                    // Loại bỏ các ký tự không hợp lệ trong tên tập tin
                    const cleanTitle = convertVietnameseChars(title).replace(/[^\w\s]/gi, '');

                    // Thay thế dấu cách bằng dấu gạch ngang
                    const filename = cleanTitle.replace(/\s+/g, '-').toLowerCase();

                    // Thêm đuôi .md
                    return `${filename}.md`;
                },
                onClickNewHugoFile() {
                    let { repository, currentDirectoryNode } = this.data

                    jPrompt("Title: ", '', 'New Hugo content', (title) => {
                        if (title) {
                            let name = title.endsWith('.md')? title : this.titleToHugoFilename(title)

                            let newNode = { name: name, isFile: true, path: currentDirectoryNode.path + '/' + name, children: []}

                            currentDirectoryNode.children.push(newNode)

                            axios({
                                method: 'post',
                                url: 'index2.php?action=new-hugo-content&name=' + name + '&repository=' + repository + '&path=' + currentDirectoryNode.path + '&verbose=0&ajax=1'
                            }).then(({data}) => {
                                if (data.success) {
                                    this.open(newNode)
                                }
                                if (data.message) {
                                    window.showMessage(data.message)
                                }
                            }).catch((err) => {
                                console.log(err)
                            }).finally(() => {
                                this.expand(currentDirectoryNode)
                            })
                        }
                    })
                }
            }
        });

        new Vue({
            el: '#editor',
            data: {
                repository: '<?php echo isset($_SESSION['repository'])? $_SESSION['repository'] : ''; ?>',
                directoryStructure: [],
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
                this.onChangeRepository()

                this.initTerminal()
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
                    const term = new Terminal({
                        cursorBlink: true,
                        theme: {
                            background: '#202020'
                        }
                    })

                    this.fitAddon = new FitAddon.FitAddon()

                    term.loadAddon(this.fitAddon)

                    term.open(document.getElementById('terminal'))

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

                    // Nếu chưa có node nào
                    if (this.directoryStructure.length == 0) {
                        this.directoryStructure = [
                            { name: 'Quick access', path: 'quick-access://', isDir: true, children: [] },
                            { name: 'Repository', path: '', repository: this.repository , isDir: true, children: [] }
                        ]
                    }

                    // Giữ quick access node và chỉ thay repository node
                    else {
                        this.$set(this.directoryStructure, 1, { name: 'Repository', path: '', repository: this.repository, isDir: true, children: [] })
                    }

                    this.expandDirectoryNode(this.directoryStructure[1])
                },
                expandDirectoryNode(node) {
                    axios({
                        method: 'get',
                        url: 'index2.php?action=get-directory-children&repository=' + node.repository + '&path=' + node.path + '&verbose=0&ajax=1'
                    }).then(({data}) => {
                        if (data.success) {
                            this.$set(node, 'children', data.children)
                            this.$set(node, 'expanded', true)
                        }
                    }).catch((err) => {
                        console.log(err)
                    })
                },
                onItemClicked(node) {
                    if (node.isFile) {
                        this.openNode(node)
                    } else {
                        this.currentDirectoryNode = node

                        if (node.expanded) {
                            this.$set(node, 'expanded', false)
                        } else {
                            this.expandDirectoryNode(node)
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

                    axios({
                        method: 'post',
                        url: 'index2.php?action=pin-to-quick-access' + '&name=' + node.name + '&path=' + node.path + '&repository=' + node.repository + '&verbose=0&ajax=1'
                    }).then(({data}) => {
                        if (data.message) {
                            window.showMessage(data.message)
                        }
                    }).catch((err) => {
                        console.log(err)
                    }).finally(() => {
                        if (this.quickAccessNode) {
                            this.expandDirectoryNode(this.quickAccessNode)
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
                            this.expandDirectoryNode(this.quickAccessNode)
                        }
                    })
                }
            }
        })
    </script>
</div>
<?php endif; ?>
