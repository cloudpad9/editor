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

