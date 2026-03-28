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

        /**
         * Panel nhập Search / Replace (emit đúng contract)
         */
