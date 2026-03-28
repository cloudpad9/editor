        Vue.component('file-item', {
            props: ['item', 'current'],
            template: `
                <li :class="{current: item == current, marked: item.marked}">
                    <div
                        class="tree-node"
                        :data-path="item.path"
                        :data-repository="item.repository"
                        :data-quick-access="item.quickAccess ? 1 : 0"
                        @click="onClickTreeNode"
                        @contextmenu.prevent.stop="onContextTreeNode"
                    >
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
                    // Nếu là quick access thì không sort
                    if (this.item.path == 'quick-access://') {
                        return this.item.children;
                    }

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

