// Old jquery version
$.browser = $.browser || {}

function append_url(base, additional) {
    var sep = (base.indexOf('?') > -1) ? '&' : '?'

    return base + sep + additional
}

function guid() {
  function s4() {
    return Math.floor((1 + Math.random()) * 0x10000)
      .toString(16)
      .substring(1)
  }
  return s4() + s4() + '-' + s4() + '-' + s4() + '-' +
    s4() + '-' + s4() + s4() + s4()
}

function ensureTabs(el, onActivate) {
    var tab = $(el)
    var selector = tab.getSelector()[0]
    var index = btoa(selector);

    var dataStore = window.sessionStorage;

    try {
        var oldIndex = dataStore.getItem(index);
    } catch(e) {
        var oldIndex = 0;
    }

    tab.tabs({
        active : oldIndex,
        activate : function( event, ui ){
            var newIndex = ui.newTab.parent().children().index(ui.newTab);

            dataStore.setItem( index, newIndex )

            if (typeof onActivate == 'function') {
                onActivate()
            }
        }
    }).tabs('refresh').show()

    /* Fix a bug with active tab index */
    if (oldIndex >= tab.find('>ul >li.ui-state-default').length) {
        oldIndex = 0
    }
    tab.tabs({ active: oldIndex })

    return tab
}

$(function() {
    ensureTabs($('.app-tabs'), function() {
        $('body').removeClass('menu-open')
    })

    $(".main-tabs, .sub-tabs").each(function(){
        ensureTabs($(this))
    })
})

$(function() {
    $("select.multiselect").each(function() {
        var select = $(this)

        var id = select.attr('id')
        var options = select.find('option')

        select.multiselect({
            header: false,
            classes: id
        })

        var ms = $('.ui-multiselect-menu.' + id + ' .ui-multiselect-checkboxes')
        var checkboxes = ms.find('li input[type="checkbox"]')
        var all = ms.find('li.all input[type="checkbox"]')
        var none = ms.find('li.none input[type="checkbox"]')

        var sync = function() {
            checkboxes.each(function() {
                var checkbox = $(this)
                var checkboxId = checkbox.attr('id')
                var optionIndex = checkboxId.replace('ui-multiselect-' + id + '-option-', '')
                var option = options[optionIndex]

                option.selected = checkbox.prop('checked')
            })
        }

        all.change(function() {
            if (all.prop('checked')) {
                none.prop('checked', '')
                checkboxes.not(none).prop('checked', 'checked')

                sync()
            }
        })

        none.change(function() {
            if (none.prop('checked')) {
                checkboxes.not(none).prop('checked', '')

                sync()
            }
        })
    })
})

var aceOpCache = {}


function getFileExtension(filename) {
    if (filename.indexOf('.') != -1) {
        return filename.split('.').pop()
    }
}

function convertToAceEditor(elements) {
    elements.each(function() {
        var self = $(this)

        if (self.data('editor')) {
            return
        }

        var autosave = self.data('autosave') != undefined? self.data('autosave') : 1
        var onchange = self.data('onchange') != undefined? self.data('onchange') : ''
        var theme = self.data('theme')? self.data('theme') : 'textmate'
        var extension = self.data('extension')? self.data('extension') : ''
        var id = self.attr('id')? self.attr('id') : guid()
        var aid = 'ace-' + id
        var mode = extension
        var tabSize = 4

        if (extension == 'vue') {
            mode = 'html'
        } else if (extension == 'js') {
            mode = 'javascript'
        } else if (extension == 'ts') {
            mode = 'typescript'
        } else if (extension == 'py') {
            mode = 'python'
        } else if (extension == 'java') {
            mode = 'java'
        } else if (extension == 'rs') {
            mode = 'rust'
        } else if (extension == 'css') {
            mode = 'css'
        } else if (extension == 'tpl') {
            mode = 'html'
        } else if (extension == 'less') {
            mode = 'less'
        } else if (extension == 'sass') {
            mode = 'sass'
        } else if (extension == 'xml') {
            mode = 'xml'
        } else if (extension == 'yaml') {
            mode = 'yaml'
        } else if (extension == 'sql') {
            mode = 'sql'
        } else if (extension == 'md') {
            mode = 'markdown'
        } else {
            mode = extension
        }

        if (extension == 'dart' || extension == 'css' || extension == 'vue' || extension == 'js') {
            tabSize = 2
        }

        $('<div/>').attr('id', aid).insertAfter(self)

        ace.require("ace/ext/language_tools");

        var editor = ace.edit(aid)
        self.addClass(aid).hide()
        self.data('editor', editor)

        editor.setOptions({
            enableBasicAutocompletion: true,
            enableLiveAutocompletion: true,
            enableSnippets: true,
            showLineNumbers: true,
            tabSize: tabSize
        })

        //editor.commands.bindKey("shift-space", "startAutocomplete")

        // editor.setTheme("ace/theme/" + theme)
        editor.setTheme("ace/theme/cloud_editor_dark")
        editor.session.setUseWorker(false)
        editor.session.setMode("ace/mode/" + mode)
        editor.$blockScrolling = Infinity

        editor.setValue(self.val(), -1)

        // Variables to hold cut content
        let cutContent = '';

        // Function to cut the current line
        function cutLine() {
            const session = editor.getSession();
            const cursor = editor.getCursorPosition();
            const line = session.getLine(cursor.row);
            cutContent = line; // Store the content to cut
            session.remove(new ace.Range(cursor.row, 0, cursor.row + 1, 0)); // Remove the line
        }

        // Function to paste the cut content
        function pasteLine() {
            if (cutContent) {
                const session = editor.getSession();
                const cursor = editor.getCursorPosition();
                session.insert({ row: cursor.row, column: 0 }, cutContent + '\n'); // Insert the cut content
            }
        }

        // Register custom key bindings
        editor.commands.addCommand({
            name: 'cutLine',
            bindKey: { win: 'Ctrl-K', mac: 'Command-K' },
            exec: cutLine,
            readOnly: false // true if this command should not apply in readOnly mode
        });

        editor.commands.addCommand({
            name: 'pasteLine',
            bindKey: { win: 'Ctrl-U', mac: 'Command-U' },
            exec: pasteLine,
            readOnly: false
        });

        editor.commands.addCommand({
            name: 'saveContent',
            bindKey: {win: 'Ctrl-S',  mac: 'Command-S'},
            exec: function(editor) {
                var content = editor.getValue()

                var textarea = $(editor.container).prev('textarea')
                textarea.val(content)

                var form = textarea.closest('form')
                $(form).trigger('submit')
            },
            readOnly: true // false if this command should not apply in readOnly mode
        })

        editor.commands.addCommand({
            name: 'search',
            bindKey: {win: 'Ctrl-F',  mac: 'Command-F'},
            exec: function(editor) {
                editor.execCommand("find")
            },
            readOnly: true // false if this command should not apply in readOnly mode
        })

        editor.commands.addCommand({
            name: 'tabsize',
            bindKey: {win: 'Ctrl-I',  mac: 'Command-I'},
            exec: function(editor) {
                editor.setOptions({
                    tabSize: editor.getOption('tabSize') == 2 ? 4 : 2
                })
            },
            readOnly: true // false if this command should not apply in readOnly mode
        })

        editor.commands.addCommand({
            name: 'goto',
            bindKey: {win: 'Ctrl-G',  mac: 'Command-G'},
            exec: function(editor) {
                var line = parseInt(prompt("Enter line number:"), 10)
                if (!isNaN(line)) {
                    editor.gotoLine(line)
                }
            },
            readOnly: true
        })

        editor.commands.addCommand({
            name: 'wrap',
            bindKey: {win: 'Ctrl-B',  mac: 'Command-B'},
            exec: function(editor) {
                let wrapMode = aceOpCache["wrapMode"] || false

                wrapMode = !wrapMode

                editor.getSession().setUseWrapMode(wrapMode)

                aceOpCache["wrapMode"] = wrapMode
            },
            readOnly: true
        })

        editor.commands.addCommand({
            name: 'toUpperCase',
            bindKey: {win: 'Ctrl-Shift-U',  mac: 'Command-Shift-U'},
            exec: function(editor) {
                let cache = aceOpCache["toUpperCase"] || ""
                let text = editor.getCopyText()

                if (cache != "") {
                    let isSame = cache.toLowerCase() == text.toLowerCase()

                    if (isSame) {
                        if (text != cache) {
                            editor.undo()
                        } else {
                            editor.toUpperCase()
                        }
                    } else {
                        aceOpCache["toUpperCase"] = text

                        editor.toUpperCase()
                    }
                } else {
                    aceOpCache["toUpperCase"] = text

                    editor.toUpperCase()
                }
            },
            readOnly: true // false if this command should not apply in readOnly mode
        })

        editor.commands.addCommand({
            name: 'toLowerCase',
            bindKey: {win: 'Ctrl-Shift-L',  mac: 'Command-Shift-L'},
            exec: function(editor) {
                let cache = aceOpCache["toLowerCase"] || ""
                let text = editor.getCopyText()

                if (cache != "") {
                    let isSame = cache.toLowerCase() == text.toLowerCase()

                    if (isSame) {
                        if (text != cache) {
                            editor.undo()
                        } else {
                            editor.toLowerCase()
                        }
                    } else {
                        aceOpCache["toLowerCase"] = text

                        editor.toLowerCase()
                    }
                } else {
                    aceOpCache["toLowerCase"] = text

                    editor.toLowerCase()
                }
            },
            readOnly: true // false if this command should not apply in readOnly mode
        })

        editor.commands.addCommand({
            name: 'toSnake',
            bindKey: {win: 'Ctrl--',  mac: 'Command--'},
            exec: function(editor) {
                let cache = aceOpCache["toSnake"] || ""
                let text = editor.getCopyText()
                let snake = text.replace(/\s+/g, "_")

                if (cache != "") {
                    let isSame = cache.toLowerCase() == text.toLowerCase()

                    if (isSame) {
                        if (text != cache) {
                            editor.undo()
                        } else {
                            editor.session.replace(editor.selection.getRange(), snake);
                        }
                    } else {
                        aceOpCache["toSnake"] = text

                        editor.session.replace(editor.selection.getRange(), snake);
                    }
                } else {
                    aceOpCache["toSnake"] = text

                    editor.session.replace(editor.selection.getRange(), snake);
                }
            },
            readOnly: true // false if this command should not apply in readOnly mode
        })

        editor.on('focus', function () {
            editor.getSession().setUseWorker(true)
        })

        editor.on('blur', function () {
            editor.getSession().setUseWorker(false)
        })

        editor.getSession().getUndoManager().reset()

        editor.getSession().on('change', function(){
            let content = editor.getValue()

            if (content.trim().length == 0 || content === 'Loading...') {
                return
            }

            self.val(content)

            let form = self.closest('form')

            if (onchange.length > 0 && typeof window[onchange] === "function") {
                window[onchange](editor)
            }

            if (window['acetimer']) {
                clearTimeout(window['acetimer'])
            }

            if (autosave) {
                window['acetimer'] = window.setTimeout(
                    function() {
                        form.trigger('submit')
                    },
                    1000
                )
            } else {
                window['acetimer'] = window.setTimeout(
                    function() {
                        form.data('autorev', 1)
                        form.trigger('submit')
                        form.data('autorev', 0)
                    },
                    30000
                )
            }
        })
    })
}

$(function() {
    convertToAceEditor($("textarea.ace-editor"))
})

//$(function() {
//    $("textarea").autogrow()
//})

// x. Ajaxable forms
function ajaxableForm($container) {
    var $forms = $container ? $container.find("form") : $("form")

    $forms.not(".native").each(function() {
        var form = $(this)

        if (form.hasClass('ajaxabled')) {
            return
        } else {
            form.addClass('ajaxabled')
        }

        var success = form.data('success')? form.data('success') : ''
        var isJSON = form.data('json')? form.data('json') : 0
        var verbose = form.data('verbose') != undefined? form.data('verbose') : 1
        var container = form.data('container') != undefined? form.data('container') : '#response'
        var has_container = (form.data('container') != undefined)
        var text_mode = (form.data('type') == 'text')
        var autosave = form.data('autosave')

        if ($(container).length == 0) {
            $('<div id="response"></div>').appendTo($('body')).hide();
        }

        form.submit(function(e) {
            e.preventDefault()

            let autorev = form.data('autorev')
            let quiet = form.data('quiet') || autosave || autorev? 1 : 0

            if (autorev) {
                verbose = 0
            }

            if (!quiet) {
                loading_start()
                $(container).html('Loading...')
            }

            var formData = new FormData(form[0])

            var http = new XMLHttpRequest()
            var url = append_url(form.attr('action'), '&ajax=1&verbose=' + verbose + (quiet? '&quiet=1' : '') + (autorev? '&autorev=1' : '') + ('&sid=' + USER_SESSION_ID))

            http.open("POST", url, true)

            http.onreadystatechange = function() {
                // IMPORTANT: check state 3 to support realtime flush
                if (http.readyState == 3 || http.readyState == 4) {
                    let completed = http.readyState == 4

                    if (http.responseText) {
                        if (success.length > 0 && typeof window[success] === "function") {
                            if (has_container) {
                                if (text_mode) {
                                    $(container).text(http.responseText)
                                } else {
                                    $(container).html(http.responseText)

                                    if (completed) {
                                        ajaxableLinks($(container))
                                        ajaxableForm($(container))
                                    }
                                }
                                $(container).scrollTop($(container).prop("scrollHeight"))
                            }

                            if (completed) {
                                window[success](http.responseText, http.readyState, form)
                            }
                        } else if (isJSON) {
                            if (completed) {
                                let json = JSON.parse(http.responseText)

                                if (json.message) {
                                    showMessage(json.message)
                                }
                            }
                        } else {
                            if (text_mode) {
                                $(container).text(http.responseText)
                            } else {
                                $(container).html(http.responseText)

                                if (completed) {
                                    ajaxableLinks($(container))
                                    ajaxableForm($(container))
                                }
                            }
                            $(container).scrollTop($(container).prop("scrollHeight"))
                        }
                    } else {
                        if (!quiet) {
                            $(container).html('')
                        }
                    }

                    if (completed) {
                        loading_end()
                    }
                }
            }

            http.send(formData)
        })
    })
}

$(function() {
    ajaxableForm()
})

function _t(key) {
    return key
}

function ensurePrompt(element, handler) {
    var promptName = element.data('prompt-name')
    var promptText = element.data('prompt-text')
    var promptRequired = element.data('prompt-required')

    if (promptName && !promptText) {
        promptText = _t('Enter a value for') + ' ' + promptName
    }

    var call_handler = function(params) {
        if (typeof handler == 'function') {
            handler(params)
        }
    }

    if (promptName) {
        jPrompt(promptText, '', 'Confirmation', function (value) {
            if (value != null) {
                if (value || !promptRequired) {
                    var params = {}

                    params[promptName] = value

                    call_handler(params)
                }
            }
        })
    } else {
        call_handler()
    }
}

function ensureMessage(element, handler) {
    // Check for confirmation
    var warning = element.data('warning')
    var confirmation = element.data('confirmation')

    if (confirmation && !warning) {
        warning = _t('Are you sure you want to perform this operation? If so, type') + ' \'' + confirmation + '\' ' + _t('to confirm') + '.'
    }

    var call_handler = function() {
        if (typeof handler == 'function') {
            ensurePrompt(element, handler)
        }
    }

    if (warning) {
        if (confirmation) {
            jPrompt(warning, '', 'Confirmation', function (text) {
                if (text == confirmation) {
                    call_handler()
                }
            })
        } else {
            jConfirm(warning, 'Confirmation', function(r) {
                if (r) {
                    call_handler()
                }
            })
        }
    } else {
        call_handler()
    }
}

function onAjaxableResponse (data, has_container, container, append, completed) {
    var json = 0
    var isJSON = (typeof data === 'object')
    var isTextJSON = !isJSON && data.trim()[0] == '{'

    if (isJSON) {
        json = data
    } else if (isTextJSON) {
        json = JSON.parse(data)
    }

    if (json) {
        if (json.message) {
            showMessage(json.message)
            return
        }

        if (json.content) {
            data = json.content;
        } else {
            return
        }
    }

    // x. In case of error
    if (data.indexOf('ERROR') != -1) {
        let match = data.match(/\[Native message\:([^\]]*)/i);

        if (match[1] && match[1].length) {
            showMessage(match[1]);
            return;
        }
    }

    if (has_container) {
        if (append) {
            $(container).append(data).show()
        } else {
            $(container).html(data).show()
        }

        if (scroll) {
            $(container).scrollTop($(container).prop("scrollHeight"))
        }

        if (completed) {
            ajaxableLinks($(container))
            ajaxableForm($(container))
        }
    }
}

// x. Ajaxable links
function ajaxableLinks($container) {
    var $links = $container ? $container.find("a.ajaxable") : $("a.ajaxable")

    $links.each(function() {
        var link = $(this)

        if (link.data('ajaxabled')) {
            return;
        }

        link.data('ajaxabled', true);

        var success = link.data('success')? link.data('success') : ''
        var verbose = link.data('verbose') != undefined? link.data('verbose') : 1
        var quiet = link.data('quiet') || false
        var append = link.data('append') || false
        var scroll = link.data('scroll') != undefined? link.data('scroll') : 1
        var container = link.data('container') != undefined? link.data('container') : '#response'
        var has_container = (link.data('container') != undefined)

        var clickHandler = null

        if (link[0].onclick) {
            clickHandler = link[0].onclick

            link.prop('onclick',null).off('click')
        } else {
            clickHandler = function(additionalRequestParams) {
                if (!quiet) {
                    loading_start()
                    $(container).html('Loading...')
                }

                var http = new XMLHttpRequest()
                var url = append_url(link.attr('href'), 'ajax=1&verbose=' + verbose)
                var params = []

                if (additionalRequestParams) {
                    for (var key in additionalRequestParams) {
                        url = append_url(url, key + '=' + additionalRequestParams[key])
                    }
                }

                http.open("GET", url, true)
                http.onreadystatechange = function() {
                    // IMPORTANT: check state 3 to support realtime flush
                    if (http.readyState == 3 || http.readyState == 4) {
                        let completed = http.readyState == 4

                        onAjaxableResponse (http.responseText, has_container, container, append, completed)

                        if (completed) {
                            if (success.length > 0 && typeof window[success] === "function") {
                                window[success](http.responseText, link)
                            }

                            loading_end()
                        }
                    }
                }

                http.send(params)
            }
        }

        link.click(function(e) {
            e.preventDefault()

            // Check for confirmation
            ensureMessage(link, clickHandler)
        })
    })
}

$(function() {
    ajaxableLinks();
})

function init_divider($el) {
    var totalHeight = $el.parent().outerHeight()

    var $upper = $el.prev()
    var $lower = $el.next()

    function ResizePage(clientY, doDrag) {
        if (clientY < 0) {
            clientY = 0
        }

        var topHeight = clientY - $upper.position().top

        if (topHeight < 0) {
            topHeight = 0
        }

        var bottomHeight = totalHeight - topHeight - $el.outerHeight()

        if (doDrag) {
            $upper.height(topHeight)
            $lower.height(bottomHeight)
            $el.css('top', '0')
        }

        return true
    }

    $el.draggable({
        axis: "y",
        drag: function (event, ui) {
            return ResizePage(event.clientY, false)
        },
        stop: function(event, ui) {
            ResizePage(event.clientY, true)
        }
    })
}

$(function() {
    $(".divider, #divider").each(function() {
        init_divider($(this))
    })
})

$(function() {
    $("#toggler").click(function() {
        $("body").toggleClass('fullscreen')
    })
})

$(function() {
    $(".js-command").click(function() {
        $("body").addClass('fullscreen')
    })
})

$(function() {
    $("#tabs .expander").click(function() {
        $("body").toggleClass('tabs-expanded')
    })
})

$(function() {
    $("form.clear-on-submit").submit(function(e) {
        $(this)[0].reset()
    })
})

var get_selector = function (element) {
    var pieces = []

    for (; element && element.tagName !== undefined; element = element.parentNode) {
        if (element.className) {
            var classes = element.className.split(' ')
            for (var i in classes) {
                if (classes.hasOwnProperty(i) && classes[i]) {
                    pieces.unshift(classes[i])
                    pieces.unshift('.')
                }
            }
        }
        if (element.id && !/\s/.test(element.id)) {
            pieces.unshift(element.id)
            pieces.unshift('#')
        }
        pieces.unshift(element.tagName)
        pieces.unshift(' > ')
    }

    return pieces.slice(1).join('')
}

$.fn.getSelector = function (only_one) {
    if (true === only_one) {
        return get_selector(this[0])
    } else {
        return $.map(this, function (el) {
            return get_selector(el)
        })
    }
}

jQuery.fn.extend({
    addCommand: function(cmd) {
        return // VIETTQ Disabled due to performance issues

        return this.each(function() {
            var input = $(this)
            var key = input.getSelector(true)
            var data = localStorage.getItem(key)
            var commands = data? JSON.parse(data) : []

            commands.push(cmd)
            commands = $.unique(commands)

            localStorage.setItem(key, JSON.stringify(commands))

            return commands
        })
    }
})

function addInputHistory(input, cmd) {
    return // VIETTQ Disabled due to performance issues

    var key = input.getSelector(true)
    var data = localStorage.getItem(key)
    var histories = data? JSON.parse(data) : []

    if (cmd) {
        histories.push(cmd)
        histories = $.unique(histories)

        localStorage.setItem(key, JSON.stringify(histories))
    }

    return histories
}

jQuery.fn.inputWithHistory = function (conf) {
    var config = jQuery.extend({}, conf)

    return this.each(function () {
        // Skip if already processed
        if (this.inputWithHistory) {
            return
        }

        var input = $(this)
        var commands = addInputHistory(input)

        // x. Command navigation
        var cmdIndex = 0

        var getCommand = function(inc) {
            cmdIndex += inc

            if (cmdIndex < 0) {
                cmdIndex = commands.length - 1
            }

            if (cmdIndex > commands.length - 1) {
                cmdIndex = 0
            }

            return commands[cmdIndex]
        }

        var setInputCommand = function(cmd) {
            input.val(cmd).attr('title', cmd)
        }

        // x. Binding
        input.keydown(function (e) { // IMPORTANT: Use 'keydown' event
            if (e.which == 40) { // down arrow
                setInputCommand(getCommand(1))
            } else if (e.which == 38) { // up arrow
                setInputCommand(getCommand(-1))
            } else if (e.which == 13) { // enter
                commands = addInputHistory(input, input.val())

                cmdIndex = commands.length - 1
            }
        })

        // Mark as processed
        this.inputWithHistory = true
    })
}

$(function() {
    $(".js-filename").inputWithHistory()

    $(".js-exec").click(function() {
        addInputHistory(input, input.val())
    })

    $(".js-submit").off('click').on('click', function(e) {
        e.preventDefault()

        var action = $(this).data('action')

        submitForm($(this), action)
    })
})

$(function() {
    function bindLiveSearchResults() {
        var repository = $('.js-repository').val()

        $('ul.live-search-results>li>span').click(function(){
            var filename = $(this).text()

            var editor = $('.editor-tabs:visible').data('editor')

            if (editor) {
                editor.editorOpenFile(filename, repository)
            }

            var input = $(".js-filename").inputWithHistory()
            input.addCommand(input.val())

            $('#jquery-live-search').hide()
        })
    }

    function bindLiveSearch() {
        var repository = $('.js-repository').val()

        var url = 'index2.php?ajax=1&verbose=0&action=file-live-search&repository=' + repository + '&filename='

        $('input.js-filename').liveSearch({url: url, onSuccess: bindLiveSearchResults})
    }

    // x. Enable live search
    bindLiveSearch()

    $('.js-repository').change(function(){
        bindLiveSearch()
    })
})

$(function() {
    $(".meter > span").each(function() {
        $(this)
            .data("origWidth", $(this).width())
            .width(0)
            .animate({
                width: $(this).data("origWidth")
            }, 1200)
    })
})

$(function() {
    $('.response').off('dblclick').on('dblclick', function() {
        $(this).toggleClass('expanded')
    })

    $('#ssh-response-extra').off('dblclick').on('dblclick', function() {
        $(this).toggle()
    })
})

function openFileInModal(url, line) {
    var editor = $('#inline-editor-content').data('editor')

    if ($('#inline-editor-content').data('url') != undefined && $('#inline-editor-content').data('url').trim() == url.trim()) {
        editor.gotoLine(line)

        $('#modal').show()

        return
    }

    var http = new XMLHttpRequest()
    var params = []

    http.open("GET", url, true)
    http.onreadystatechange = function() {
        if (http.readyState == 4) {
            $('#inline-editor-content').data('url', url)

            editor.setValue(http.responseText, -1)
            editor.gotoLine(line)

            $('#modal').show()
            editor.gotoLine(line)
        }
    }

    http.send(params)
}

function switchToEditor() {
    var app_tabs = $(".app-tabs")
    var tab_li = app_tabs.find('a[href="#tabs-editor"]').closest('li')
    var index = tab_li.index()

    app_tabs.tabs("option", "active", index)
}

var $gEditor = null

function gotoLine(number) {
    $gEditor.gotoLine(number)
}

function onSearchDone(content, readyState) {
    if (readyState != 4) {
        return
    }

    var ensureFileContextMenu = function (file_li) {
        var getMenuPosition = function (mouse, direction, scrollDir) {
            var win = $(window)[direction](),
                scroll = $(window)[scrollDir](),
                menu = $('#xxx')[direction](),
                position = mouse + scroll

            // opening menu would pass the side of the page
            if (mouse + menu > win && menu < mouse)
                position -= menu

            return position
        }

        file_li.contextmenu(function(e) {
            if (e.ctrlKey) return

            var item = $(this).closest('.snr-item')
            var repository = item.data('repository')
            var file = item.data('file')

            // Hide other context menus
            $('.contextmenu').hide()

            var mnu_open = $('<li>Open</li>').click(function() {
                window.dev_editor.editorOpenFile(file, repository)

                switchToEditor()
            })

            var sep = '<li class="sep"></li>'

            var menu = $('<ul id="xxx" class="dropdown-menu"></ul>')
                .append(mnu_open)

            // Open menu
            menu.appendTo($('body'))
                .addClass('contextmenu')
                .show()
                .css({
                    position: "absolute",
                    left: getMenuPosition(e.clientX, 'width', 'scrollLeft'),
                    top: getMenuPosition(e.clientY, 'height', 'scrollTop')
                })

            // Make sure menu closes on any click
            $('body').click(function () {
                menu.hide()
            })

            return false
        })
    }

    $(".js-snr-file").each(function() {
        var link = $(this)

        link.click(function() {
            var url = link.data('url')
            var line = link.data('line') != undefined? link.data('line') : 1

            openFileInModal(append_url(url, 'ajax=1&verbose=0'), line)
        })
    })

    $(".snr-item>.snr-item-header").each(function() {
        var item = $(this)

        item.click(function() {
            item.parent().toggleClass('collapsed')
        })

        ensureFileContextMenu(item)
    })
}

class Editor {
    constructor(options) {
        options = options || {}

        if ($(options.el).length == 0) {
            console.error('[Editor] Editor container not found')

            return
        }

        let files = options.files || []
        let style = options.style || 1

        this.$el = $(options.el)
        this.$ul = this.$el.find(".js-tabs-nav")
        this.$tabsContainer = this.$el.find(".js-tabs-container")
        this.style = style

        this.$el.data('editor', this)

        if (Object.keys(files).length > 0) {
            this.editorOpenFiles(files)
        } else {
            this.editorNewFile()
        }

        // Bắt sự kiện click trên tab để emit vào bus
        this._recentTabKey = null

        this.$ul.off('click.editor').on('click.editor', 'a.tab_title', (e) => {
            const $li = $(e.currentTarget).closest('li')

            const repository = ($li.data('repository') || '').toString()
            const path = ($li.data('filename') || '').toString()

            const key = repository + '|' + path

            if (key === this._recentTabKey) return

            this._recentTabKey = key

            if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
                window.editorEventBus.$emit('editor-tab-clicked', { repository, path })
            }
        })

        if (!MobileDetect.any()) {
            this.$ul.sortable()
        }
    }

    editorNewFile() {
        let that = this
        let url = 'index2.php?ajax=1&verbose=0&action=new-temp-file'

        $.getJSON(url, function(json) {
            if (json.success) {
                that.createNewEditorTab(json.filename, json.repository, json.content)
            } else {
                showMessage(json.message)
            }
        })
    }

    createNewEditorTab(filename, repository, content, color, loadContentLater) {
        var id = uniqid()
        var num_tabs = this.$ul.find("li").length + 1

        var basename = filename.split('\\').pop().split('/').pop()
        var extension = getFileExtension(filename) || ''

        var li = $('<li data-filename="' + filename + '" data-repository="' + repository + '" class="tab_btn"><div class="tab_shadow"></div><div class="tab_middle"><a class="tab_title" href="#editor-tabs-' + id + '" title="' + filename + '">' + basename + '</a><span class="btn-close-tab"><span>x</span></span></div></li>')

        li.data('editor-id', id)

        if (loadContentLater) {
            li.data('loadContentLater', true)
        }

        set_tab_li_color(li, color)

        this.$ul.append(li)

        var is_temp_file = isTempFile(filename)
        var autosave = is_temp_file? 1 : 0

        var form = $('<form action="index2.php" method="POST" enctype="multipart/form-data">')
        form.append('<input type="hidden" name="action" value="save-current-file"/>')
        form.append('<input type="hidden" name="filename" value="' + filename + '"/>')
        form.append('<input type="hidden" name="repository" value="' + repository + '"/>')
        form.append('<textarea name="content" id="editor-content-' + id + '" class="ace-editor" data-onchange="onEditorContentChanged" data-autosave="' + autosave + '" data-theme="textmate" data-extension="' + extension + '" rows="5"></textarea>')
        form.data('autosave', autosave)

        var div = $("<div id='editor-tabs-" + id + "'></div>")
        div.append(form)

        this.$tabsContainer.append(div)

        var tabs = ensureTabs(this.$el)

        tabs.tabs("refresh")
        tabs.tabs({ active: num_tabs - 1 })

        this.setEditorTabContent(li, content)

        // Button close tab
        if (isStandalone()) {
            $("span.btn-close-tab").hide();
        }

        var that = this

        $("span.btn-close-tab").off("click").on("click", function() {
            var li = $(this).closest("li");
            var filename = li.data('filename')
            var repository = li.data('repository')
            var is_temp_file = (filename[0] == '*')

            if (is_temp_file) {
                jPrompt('Do you want to delete ' + filename + '? If so, type YES to confirm.', '', 'Confirmation', function (text) {
                    if (text == 'YES') {
                        var panelId = li.remove().attr("aria-controls")

                        $("#" + panelId ).remove()

                        tabs.tabs("refresh")
                        that.editorReloadFile(null, true, true)

                        that.editorCloseFile(filename, repository)
                    }
                })
            } else {
                var panelId = li.remove().attr("aria-controls")

                $("#" + panelId ).remove()

                tabs.tabs("refresh")
                that.editorReloadFile(null, true, true)

                that.editorCloseFile(filename, repository)
            }
        })

        // Ensure tabs's context menus
        if (!isStandalone()) {
            this.editorEnsureTabsContextMenus(li)
        }

        // Ajaxable form
        ajaxableForm()
    }

    getEditor(tab_li) {
        let panelId = tab_li.attr("aria-controls")
        let textarea = $('#' + panelId).find('textarea')
        let editor = textarea.data('editor')

        return editor
    }

    setEditorTabContent(tab_li, content) {
        var id = tab_li.data('editor-id')
        var filename = tab_li.data('filename')

        convertToAceEditor($("#editor-content-" + id))

        var editor = $("#editor-content-" + id).data('editor')

        if (editor) {
            editor.setValue(content, -1)
            editor.session.getUndoManager().reset()
        }
    }

    editorCloseFile(filename, repository) {
        if (filename.length == 0) {
            return
        }

        var url = 'index2.php?ajax=1&verbose=0&action=close-file&filename=' + filename + '&repository=' + repository + '&standalone=' + isStandalone()

        $.get(url)
    }

    editorOpenFiles(files_by_repository) {
        // Just open tabs with later content loading
        for (let repository in files_by_repository) {
            let files = files_by_repository[repository]

            for (let i = 0; i < files.length; i++) {
                this.editorOpenFile(files[i], repository, true)
            }
        }

        // Setup later content loading handler
        this.editorSetupLaterContentLoading()

        // Load content of the current tab
        this.editorReloadFile()
    }

    editorOpenFile(filename, repository, loadContentLater) {
        if (filename.length == 0) {
            return
        }

        if (loadContentLater) {
            this.createNewEditorTab(filename, repository, '', '', loadContentLater)
        } else {
            var url = 'index2.php?ajax=1&verbose=0&action=get-file-content&repository=' + repository + '&filename=' + filename
            var that = this

            $.getJSON(url, function(json) {
                if (json.success) {
                    that.createNewEditorTab(json.filename, json.repository, json.content, json.color)
                } else {
                    showMessage(json.message)
                }
            })
        }
    }

    editorCloseAllFiles() {
        $("span.btn-close-tab").trigger('click')
    }

    editorCloseAllFilesToTheRight(tab_li) {
        if (!tab_li) return

        var index = tab_li.index()
        var that = this

        this.$ul.find("li").each(function() {
            var li = $(this)

            if (li.index() > index) {
                that.editorCloseTab(li)
            }
        })
    }

    editorCloseTab(tab_li) {
        tab_li = tab_li || this.$ul.find("li.ui-state-active")

        tab_li.find("span.btn-close-tab").trigger('click')
    }

    editorSetCurrentTabColor(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')
        var currentColor = tab_li.data('color')

        jPrompt("Color: ", currentColor? currentColor : 'yellow/black', 'Set current tab color', function (color) {
            set_tab_li_color(tab_li, color)

            var url = 'index2.php?ajax=1&verbose=0&action=set-color&filename=' + filename + '&repository=' + repository + '&color=' + encodeURIComponent(color)

            $.getJSON(url, function(json) {
                if (!json.success) {
                    showMessage(json.message)
                }
            })
        })
    }

    editorOpenInStandalone(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')
        var url = 'index.php?action=open-file&standalone=1&filename=' + filename + '&repository=' + repository

        window.open(url, '_blank')
    }

    editorCloneFile(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')
        var basename = filename.split('\\').pop().split('/').pop()
        var that = this

        jPrompt("New filename: ", basename, 'Copy ' + filename, function (newname) {
            if (newname) {
                var url = 'index2.php?ajax=1&verbose=0&action=clone-file&newname=' + newname + '&filename=' + filename + '&repository=' + repository

                $.getJSON(url, function(json) {
                    if (json.success) {
                        that.createNewEditorTab(json.filename, json.repository, json.content)
                    } else {
                        showMessage(json.message)
                    }
                })
            }
        })
    }

    editorRenameFile(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')
        var basename = filename.split('\\').pop().split('/').pop()
        var that = this

        jPrompt("New filename: ", basename, 'Rename ' + filename, function (newname) {
            if (newname) {
                var url = 'index2.php?ajax=1&verbose=0&action=rename-file&newname=' + newname + '&filename=' + filename + '&repository=' + repository

                $.getJSON(url, function(json) {
                    if (json.success) {
                        // Close current tab
                        that.editorCloseTab(tab_li)

                        // Create new tab
                        that.createNewEditorTab(json.filename, json.repository, json.content)
                    } else {
                        showMessage(json.message)
                    }
                })
            }
        })
    }

    editorRenameDirectory(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')
        var dirname = filename.substring(0, filename.lastIndexOf('/'))
        var basename = dirname.split('/').pop()
        var that = this

        jPrompt("New directory name: ", basename, 'Rename directory', function (newname) {
            if (newname) {
                var url = 'index2.php?ajax=1&verbose=0&action=rename-directory-of-file&newname=' + newname + '&filename=' + filename + '&repository=' + repository

                $.getJSON(url, function(json) {
                    if (json.success) {
                        // Close current tab
                        that.editorCloseTab(tab_li)

                        // Create new tab
                        that.createNewEditorTab(json.filename, json.repository, json.content)
                    } else {
                        showMessage(json.message)
                    }
                })
            }
        })
    }

    editorNewDirectory(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')
        var dirname = filename.substring(0, filename.lastIndexOf('/'))
        var basename = dirname.split('/').pop()
        var that = this

        jPrompt("Directory name: ", '../' + basename, 'New directory', function (name) {
            if (name) {
                var url = 'index2.php?ajax=1&verbose=0&action=new-directory&name=' + name + '&path=' + dirname + '&repository=' + repository

                $.getJSON(url, function(json) {
                    if (json.message) {
                        showMessage(json.message)
                    }
                })
            }
        })
    }

    editorSetupLaterContentLoading() {
        let that = this

        that.$el.click('tabsselect', function (event, ui) {
            let tab_li = that.$ul.find('li.ui-tabs-active')

            let loadContentLater = tab_li.data('loadContentLater')

            if (loadContentLater) {
                that.editorReloadFile(null, true)

                tab_li.data('loadContentLater', false)
            }
        })
    }

    editorReloadFile(tab_li, replaceCurrentTab, reloadTempFileOnly) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        if (!tab_li.length) {
            return
        }

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')
        var is_temp_file = (filename[0] == '*')

        if (!is_temp_file && reloadTempFileOnly) {
            return
        }

        var url = 'index2.php?ajax=1&verbose=0&action=reload-file&filename=' + filename + '&repository=' + repository

        if (isTempFile(filename)) {
            replaceCurrentTab = true
        }

        if (replaceCurrentTab) {
            this.setEditorTabContent(tab_li, 'Loading...')
        }

        var that = this

        $.getJSON(url, function(json) {
            if (json.success) {
                if (replaceCurrentTab) {
                    that.setEditorTabContent(tab_li, json.content)
                } else {
                    // Close current tab
                    that.editorCloseTab(tab_li)

                    // Create new tab
                    that.createNewEditorTab(json.filename, json.repository, json.content)
                }
            } else {
                showMessage(json.message)
            }
        })
    }

    editorBeautifyFile(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')

        var url = 'index2.php?ajax=1&verbose=0&action=beautify-file&filename=' + filename + '&repository=' + repository
        var that = this

        $.getJSON(url, function(json) {
            if (json.success) {
                // Close current tab
                that.editorCloseTab(tab_li)

                // Create new tab
                that.createNewEditorTab(json.filename, repository, json.content)
            } else {
                showMessage(json.message)
            }
        })
    }

    editorSyncFile(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')

        var url = 'index.php?ajax=1&verbose=0&action=sync-file&filename=' + filename + '&repository=' + repository

        $.getJSON(url, function(json) {
            if (json.message) {
                showMessage(json.message)
            }
        })
    }

    editorRevertSyncFile(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')

        jPrompt('Do you want to revert sync ' + filename + '? If so, type YES to confirm.', '', 'Confirmation', function (text) {
            if (text == 'YES') {
                var url = 'index.php?ajax=1&verbose=0&action=revert-sync-file&filename=' + filename + '&repository=' + repository

                $.getJSON(url, function(json) {
                    if (json.message) {
                        showMessage(json.message)
                    }
                })
            }
        })
    }

    editorRebuildSubIndexes(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')

        var url = 'index.php?ajax=1&verbose=0&action=rebuild-sub-indexes&filename=' + filename + '&repository=' + repository

        $.getJSON(url, function(json) {
            if (json.message) {
                showMessage(json.message)
            }
        })
    }

    editorEnsureTabsContextMenus(tab_li) {
        var getMenuPosition = function (mouse, direction, scrollDir) {
            var win = $(window)[direction](),
                scroll = $(window)[scrollDir](),
                menu = $('#xxx')[direction](),
                position = mouse + scroll

            // opening menu would pass the side of the page
            if (mouse + menu > win && menu < mouse)
                position -= menu

            return position
        }

        var that = this

        tab_li.contextmenu(function(e) {
            if (e.ctrlKey) return

            // Hide other context menus
            $('.contextmenu').hide()

            var mnu_open_standalone = $('<li>Open in standalone</li>').click(function() {
                that.editorOpenInStandalone(tab_li)
            })

            var mnu_new = $('<li>New</li>').click(function() {
                that.editorNewFile()
            })

            var mnu_clone = $('<li>Clone</li>').click(function() {
                that.editorCloneFile(tab_li)
            })

            var mnu_close = $('<li>Close tab</li>').click(function() {
                that.editorCloseTab(tab_li)
            })

            var mnu_close_all = $('<li>Close all tabs</li>').click(function() {
                that.editorCloseAllFiles()
            })

            var mnu_close_all_right = $('<li>Close tabs to the right</li>').click(function() {
                that.editorCloseAllFilesToTheRight(tab_li)
            })

            var mnu_rename = $('<li>Rename</li>').click(function() {
                that.editorRenameFile(tab_li)
            })

            var mnu_rename_directory = $('<li>Rename directory</li>').click(function() {
                that.editorRenameDirectory(tab_li)
            })

            var mnu_new_directory = $('<li>New directory</li>').click(function() {
                that.editorNewDirectory(tab_li)
            })

            var mnu_copy_path = $('<li>Copy path to clipboard</li>').click(function() {
                that.editorCopyFilePath(tab_li)
            })

            var mnu_pin_to_quick_access = $('<li>Pin to quick access</li>').click(function() {
                that.editorPinToQuickAccess(tab_li)
            })

            var mnu_open_file_location = $('<li>Open file location</li>').click(function() {
                that.editorOpenFileLocation(tab_li)
            })

            var mnu_download_file = $('<li>Download</li>').click(function() {
                that.editorDownloadFile(tab_li)
            })

            var mnu_git_status = $('<li>Git status</li>').click(function() {
                that.editorGitStatus(tab_li)
            })

            var mnu_git_pull = $('<li>Git pull</li>').click(function() {
                that.editorGitPull(tab_li)
            })

            var mnu_git_diff = $('<li>Git diff</li>').click(function() {
                that.editorGitDiff(tab_li)
            })

            var mnu_git_diff_all = $('<li>Git diff all</li>').click(function() {
                that.editorGitDiffAll(tab_li)
            })

            var mnu_apply_patch = $('<li>Apply patch</li>').click(function() {
                that.editorApplyPatch(tab_li)
            })

            var mnu_git_revert = $('<li>Git revert</li>').click(function() {
                that.editorGitRevert(tab_li)
            })

            var mnu_git_commit = $('<li>Git commit</li>').click(function() {
                that.editorGitCommit(tab_li)
            })

            var mnu_git_commit_all = $('<li>Git commit all</li>').click(function() {
                that.editorGitCommitAll(tab_li)
            })

            var mnu_git_log = $('<li>Git log</li>').click(function() {
                that.editorGitLog(tab_li)
            })

            var mnu_git_log_all = $('<li>Git log all</li>').click(function() {
                that.editorGitLogAll(tab_li)
            })

            var mnu_view_function_list = $('<li>View function list</li>').click(function() {
                that.editorViewFunctionList(tab_li)
            })

            var mnu_inspect_vue_content = $('<li>Inspect Vue content</li>').click(function() {
                that.editorInspectVueContent(tab_li)
            })

            var mnu_ensure_namespace = $('<li>Ensure namespace</li>').click(function() {
                that.editorEnsureNamespace(tab_li)
            })

            var mnu_revert = $('<li>Revert</li>').click(function() {
                that.editorRevertFile(tab_li)
            })

            var mnu_reload = $('<li>Reload</li>').click(function() {
                that.editorReloadFile(tab_li)
            })

            var mnu_beautity = $('<li>Beautity</li>').click(function() {
                that.editorBeautifyFile(tab_li)
            })

            var mnu_color = $('<li>Color</li>').click(function() {
                that.editorSetCurrentTabColor(tab_li)
            })

            var mnu_sync = $('<li>Sync</li>').click(function() {
                that.editorSyncFile(tab_li)
            })

            var mnu_revert_sync = $('<li>Revert sync</li>').click(function() {
                that.editorRevertSyncFile(tab_li)
            })

            var mnu_rebuild_indexes = $('<li>Rebuild sub-indexes</li>').click(function() {
                that.editorRebuildSubIndexes(tab_li)
            })

            var sep = '<li class="sep"></li>'

            var menu = $('<ul id="xxx" class="dropdown-menu"></ul>')
                .append(mnu_open_standalone)
                .append(sep)
                .append(mnu_new)
                .append(mnu_clone)
                .append(mnu_close)
                .append(mnu_close_all)
                .append(mnu_close_all_right)
                .append(sep)
                .append(mnu_download_file)
                .append(sep)
                .append(mnu_open_file_location)
                .append(sep)
                .append(mnu_copy_path)
                .append(sep)
                .append(mnu_pin_to_quick_access)
                .append(sep)
                .append(mnu_apply_patch)
                .append(sep)
                .append(mnu_git_status)
                .append(mnu_git_pull)
                .append(mnu_git_diff)
                .append(mnu_git_diff_all)
                .append(mnu_git_commit)
                .append(mnu_git_commit_all)
                .append(mnu_git_revert)
                .append(mnu_git_log)
                .append(mnu_git_log_all)
                .append(sep)
                .append(mnu_view_function_list)
                .append(sep)
                .append(mnu_inspect_vue_content)
                .append(sep)
                .append(mnu_ensure_namespace)

            if (that.style == 2) {
                menu
                .append(sep)
                .append(mnu_rename)
                .append(mnu_rename_directory)
                .append(mnu_new_directory)
                .append(mnu_revert)
                .append(mnu_reload)
                .append(mnu_beautity)
                .append(sep)
                .append(mnu_color)
                .append(sep)
                .append(mnu_sync)
                .append(mnu_revert_sync)
                .append(sep)
                .append(mnu_rebuild_indexes)
            }

            // Open menu
            menu.appendTo($('body'))
                .addClass('contextmenu')
                .show()
                .css({
                    position: "absolute",
                    left: getMenuPosition(e.clientX, 'width', 'scrollLeft'),
                    top: getMenuPosition(e.clientY, 'height', 'scrollTop')
                })

            // Make sure menu closes on any click
            $('body').click(function () {
                menu.hide()
            })

            return false
        })
    }

    editorGetTabByFileName(filename) {
        return this.$ul.find('li[data-filename="' + filename + '"]')
    }

    editorSetFileAsModified(filename) {
        var li = this.editorGetTabByFileName(filename)
        var basename = filename.split('\\').pop().split('/').pop()

        li.css('background', 'orange')
        showMessage("File '" + basename + "' has been modified from another session.")
    }

    setEditorContent(content, readyState, form) {
        if (readyState == 4) {
            var filename = form.find('input[name="filename"]').val()

            this.createNewEditorTab(filename, '', content)
        }
    }

    editorCopyFilePath(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')

        var url = 'index2.php?ajax=1&verbose=0&action=get-full-file-path&path=' + filename + '&repository=' + repository

        $.getJSON(url, function(json) {
            if (json.success) {
                // IMPORTANT: clipboard functions only works with user's direct events
                showMessage('Done', function(){
                    copyTextToClipboard(json.path)
                })
            } else {
                showMessage(json.message)
            }
        })
    }

    editorPinToQuickAccess(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('pin-to-quick-access', { repository, path })
        } else {
            let errorMessage = 'Không thể xem pin to quick access. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorOpenFileLocation(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorComponent && typeof window.editorComponent.expandPathToNode === 'function') {
            window.editorComponent.expandPathToNode(repository, path)
        } else {
            let errorMessage = 'Không thể mở file location. ';

            if (!window.editorComponent) {
                errorMessage += 'Explorer component chưa được khởi tạo.';
            } else if (typeof window.editorComponent.expandPathToNode !== 'function') {
                errorMessage += 'Method expandPathToNode không khả dụng.';
            }

            showMessage(errorMessage);
        }
    }

    editorDownloadFile(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorComponent && typeof window.editorComponent.downloadFile === 'function') {
            window.editorComponent.downloadFile(repository, path)
        } else {
            let errorMessage = 'Không thể tải file. ';

            if (!window.editorComponent) {
                errorMessage += 'Explorer component chưa được khởi tạo.';
            } else if (typeof window.editorComponent.downloadFile !== 'function') {
                errorMessage += 'Method downloadFile không khả dụng.';
            }

            showMessage(errorMessage);
        }
    }

    editorGitStatus(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('git-status', { repository, path })
        } else {
            let errorMessage = 'Không thể xem git status. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorGitPull(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('git-pull', { repository, path })
        } else {
            let errorMessage = 'Không thể xem git pull. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorGitDiff(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('git-diff', { repository, path })
        } else {
            let errorMessage = 'Không thể xem git diff. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorGitDiffAll(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('git-diff-all', { repository, path })
        } else {
            let errorMessage = 'Không thể xem git diff all. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorApplyPatch(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('apply-patch', { repository, path })
        } else {
            let errorMessage = 'Không thể xem apply patch. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorGitRevert(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('git-revert', { repository, path })
        } else {
            let errorMessage = 'Không thể git revert. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorGitCommit(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('git-commit', { repository, path })
        } else {
            let errorMessage = 'Không thể git commit. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorGitCommitAll(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('git-commit-all', { repository, path })
        } else {
            let errorMessage = 'Không thể git commit all. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorGitLog(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('git-log', { repository, path })
        } else {
            let errorMessage = 'Không thể git log. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorGitLogAll(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var repository = tab_li.data('repository')
        var path = tab_li.data('filename')

        if (window.editorEventBus && typeof window.editorEventBus.$emit === 'function') {
            window.editorEventBus.$emit('git-log-all', { repository, path })
        } else {
            let errorMessage = 'Không thể git log all. ';

            if (!window.editorEventBus || typeof window.editorComponent.$emit !== 'function') {
                errorMessage += 'Editor event bus chưa được khởi tạo.';
            }

            showMessage(errorMessage);
        }
    }

    editorViewFunctionList(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        // IMPORTANT: remember corresponding editor instance
        $gEditor = this.getEditor(tab_li)

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')

        var url = 'index2.php?ajax=1&verbose=0&action=dev/get-function-list&filename=' + filename + '&repository=' + repository
        var that = this

        $.getJSON(url, function(json) {
            if (json.success) {
                showMessage(json.functions.join("<br/>"))
            } else {
                showMessage(json.message)
            }
        })
    }

    editorInspectVueContent(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        // IMPORTANT: remember corresponding editor instance
        $gEditor = this.getEditor(tab_li)

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')

        var url = 'index2.php?ajax=1&verbose=0&action=dev/inspect-vue-content&filename=' + filename + '&repository=' + repository
        var that = this

        $.getJSON(url, function(json) {
            if (json.success) {
                showMessage(json.functions.join("<br/>"))
            } else {
                showMessage(json.message)
            }
        })
    }

    editorEnsureNamespace(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')

        var url = 'index2.php?ajax=1&verbose=0&action=dev/ensure-namespace&filename=' + filename + '&repository=' + repository
        var that = this

        $.getJSON(url, function(json) {
            if (json.success) {
                // Close current tab
                that.editorCloseTab(tab_li)

                // Create new tab
                that.createNewEditorTab(json.filename, repository, json.content)
            } else {
                showMessage(json.message)
            }
        })
    }

    editorRevertFile(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')

        var url = 'index2.php?ajax=1&verbose=0&action=revert-file&filename=' + filename + '&repository=' + repository
        var that = this

        $.getJSON(url, function(json) {
            if (json.success) {
                // Close current tab
                that.editorCloseTab(tab_li)

                // Create new tab
                that.createNewEditorTab(json.filename, repository, json.content)
            } else {
                showMessage(json.message)
            }
        })
    }

    editorRecoverFile(tab_li) {
        tab_li = tab_li || this.$ul.find('li.ui-tabs-active')

        var filename = tab_li.data('filename')
        var repository = tab_li.data('repository')

        var url = 'index2.php?ajax=1&verbose=0&action=recover-file&filename=' + filename + '&repository=' + repository
        var that = this

        $.getJSON(url, function(json) {
            if (json.success) {
                // Close current tab
                that.editorCloseTab(tab_li)

                // Create new tab
                that.createNewEditorTab(json.filename, repository, json.content)
            } else {
                showMessage(json.message)
            }
        })
    }

}

function onSearchAndReplaceResponse() {

}

function copyTextToClipboard(text) {
   var textArea = document.createElement( "textarea" )
   textArea.value = text
   document.body.appendChild( textArea )

   textArea.select()

   try {
      var successful = document.execCommand( 'copy' )
      var msg = successful ? 'successful' : 'unsuccessful'
      console.log('Copying text command was ' + msg)
   } catch (err) {
      console.log('Oops, unable to copy')
   }

   document.body.removeChild( textArea )
}

function loading_start() {
    $('body').addClass('loading')
}

function loading_end() {
    $('body').removeClass('loading')
}

function submitForm(element, action) {
    var form = $(element).closest('form')

    if (action) {
        form.find('[name="action"]').val(action)
    }

    form.trigger('submit')
}

function ssh_exec(element, cmd) {
    ensureMessage($(element), function() {
        var commandInput = $('#ssh_command')

        commandInput.val(cmd)

        submitForm(commandInput, 'ssh-exec-custom')
    })
}

function ssh_exec_quiet(element, cmd) {
    ensureMessage($(element), function() {
        loading_start()
        $('#ssh-response-extra').html('Executing...').show()

        $.ajax({
            type: "post",
            url: "index.php",
            data: {
                action: 'ssh-exec-quiet',
                commands: cmd,
                ajax: 1
            }
        }).done(function(response) {
            loading_end()

            $('#ssh-response-extra').html(response)
        })
    })
}

function uniqid(prefix, separator){
    prefix = prefix || ''
    separator = separator || ''

    function chr4(){
        return Math.random().toString(16).slice(-4)
    }

    return prefix + chr4() + chr4()
        + separator + chr4()
        + separator + chr4()
        + separator + chr4()
        + separator + chr4() + chr4() + chr4()
}

function set_tab_li_color(li, color) {
    color = color || ''

    var background_foreground = color.split('/')

    var background = background_foreground[0]
    var foreground = background_foreground[1]? background_foreground[1] : ''

    li.data('color', color)
    li.css('background', background)
    li.find('a').css('color', foreground)
    li.find('span').css('color', foreground)
}

function isTempFile(filename) {
    return (filename[0] == '*')
}

function isStandalone() {
    var query = window.location.search.substring(1);
    var standalone = query.indexOf('standalone=1') != -1

    return standalone
}

function onEditorContentChanged(editor) {
    if (editor.session.getUndoManager().hasUndo()) {
        $('.editor-button-save').show()
    } else {
        $('.editor-button-save').hide()
    }
}

function editorSave() {
    $('.editor-button-save').trigger('click')
}

var intervalPremise

function setTimerInfo(remaining_to_break_secs) {
    $('.work-duration').hide();

    var div = $('body > .timer-display')

    if (!div.length) {
        div = $('<div class="timer-display"></div>').appendTo($('body')).hide()
    }

    var duration = remaining_to_break_secs
    var timer = duration, minutes, seconds, display

    if (intervalPremise) {
        clearInterval(intervalPremise)
    }

    intervalPremise = setInterval(function () {
        minutes = parseInt(timer / 60, 10)
        seconds = parseInt(timer % 60, 10)

        minutes = minutes < 10 ? "0" + minutes : minutes
        seconds = seconds < 10 ? "0" + seconds : seconds

        timer -= 1

        div.text(minutes + ":" + seconds).show()

        if (timer <= -1) {
            clearInterval(intervalPremise)
            setTimout(function() {
                showMessage('TIMEOUT! Please save your work. Application will be refreshed within 5 seconds.')

                location.reload()
            }, 5000)
        }
    }, 1000)
}

function showAccumulatedWorkTime(accumulated_work_time_secs) {
    $('.timer-display').hide();

    var div = $('body > .work-duration')

    if (!div.length) {
        div = $('<div class="work-duration" title="Accumulated work time"></div>').appendTo($('body')).hide()
    }

    let timer = accumulated_work_time_secs

    let minutes = parseInt(timer / 60, 10)

    div.text(minutes + " min").show()
}

var countDownInterval

function countDown(remaining_secs, elementid) {
    var timer = remaining_secs, minutes, seconds

    if (countDownInterval) {
        clearInterval(countDownInterval)
    }

    countDownInterval = setInterval(function () {
        minutes = parseInt(timer / 60, 10)
        seconds = parseInt(timer % 60, 10)

        minutes = minutes < 10 ? "0" + minutes : minutes
        seconds = seconds < 10 ? "0" + seconds : seconds

        timer -= 1

        document.getElementById(elementid).textContent = minutes + ":" + seconds

        if (timer <= -1) {
            clearInterval(countDownInterval)
            location.reload()
        }
    }, 1000)
}


function showMessage(message, callback) {
    jAlert(message, 'CloudPad9', callback)
}

function showNotification(message) {
    let div = $('#notification')

    if (div.length == 0) {
        div = $('<div id="notification"></div>').appendTo($('body'))
    }

    div.html(message).show()

    setTimeout(function() {
        div.fadeOut(1500)
    }, 1000)
}

///////////////////////////////////////////////////////////////////////////////
// ANGULAR
///////////////////////////////////////////////////////////////////////////////
/*var app = angular.module('app', [])

app.controller('TimerController', ['$scope', '$http', '$timeout', '$interval', function($scope, $http, $timeout, $interval) {
    $scope.time = '30'; // minutes
    $scope.blockingTime = '15'; // minutes
    $scope.message = 'Timeout'
    $scope.running = false
    $scope.display = ''
    $scope.forcePlaySound = true

    var intervalPremise = null

    var playBlockingSound = function() {
        var audio = new Audio('http://www.soundjay.com/misc/bell-ringing-01.mp3')
        audio.play()
    }

    var playUnblockingSound = function() {
        var audio = new Audio('http://www.soundjay.com/misc/bell-ringing-01.mp3')
        audio.play()
    }

    var blockScreen = function() {
        $('body').addClass('blocked')
    }

    var unblockScreen = function() {
        $('body').removeClass('blocked')
    }

    var ensureBlocking = function() {
        setTimer($scope.blockingTime, function() {
            unblockScreen()
            playUnblockingSound()

            $scope.cancelTimer()
            $scope.setTimer()
        })
    }

    var onTimeout = function() {
        $scope.cancelTimer()
        blockScreen()

        if ($scope.forcePlaySound) {
            playBlockingSound()
        }

        ensureBlocking()
    }

    var setTimer = function(durationMinutes, callback) {
        $scope.display = null

        var duration = durationMinutes * 60
        var timer = duration, minutes, seconds

        intervalPremise = $interval(function () {
            minutes = parseInt(timer / 60, 10)
            seconds = parseInt(timer % 60, 10)

            minutes = minutes < 10 ? "0" + minutes : minutes
            seconds = seconds < 10 ? "0" + seconds : seconds

            $scope.display = minutes + ":" + seconds

            if (--timer < 0) {
                callback()
            }
        }, 1000)

        $scope.running = true
    }

    $scope.setTimer = function() {
        setTimer($scope.time, function() {
            onTimeout()
        })
    }

    $scope.cancelTimer = function() {
        $interval.cancel(intervalPremise)

        $scope.running = false
    }
}])*/

$(function() {
    var key = $('input.label-key')
    var input = $('input.label-text')

    input.on("keydown", function(e) {
        if (e.keyCode == 13) {
            $.ajax({
                type: "get",
                url: "http://force.vn/a/adminlanguageitem/updateTranslation",
                data: {key: key.val(), text: input.val()}
            })
        }
        e.stopPropagation()
    })
})

///////////////////////////////////////////////////////////////////////////////
// BING SPEAK
///////////////////////////////////////////////////////////////////////////////
const AppState = {
    speakSlow: false,
    isSpeechPlaying: false,
    isSpeechPaused: false,
    bingSpeechToken: '',
    bingSpeechTimeout: null
};

function ajaxBingSpeechError(response) {
    console.log(response, 'ERROR');
}

function ajaxBingSpeechCallback(response) {
    response = response.replace("http://api.microsofttranslator.com", "//api.microsofttranslator.com");

    var audio = document.getElementById('audio');

    audio.onended = function() {
        AppState.isSpeechPlaying = false;
        AppState.isSpeechPaused = false;
        //AppState.speakSlow = !AppState.speakSlow;

        $('body').removeClass('audio-playing');
    };
    audio.onpause = function() {
        AppState.isSpeechPlaying = false;
        AppState.isSpeechPaused = true;

        $('body').removeClass('audio-playing');
    };
    audio.onplay = function() {
        AppState.isSpeechPlaying = true;
        AppState.isSpeechPaused = false;

        $('body').addClass('audio-playing');
    };

    audio.src = response;
    audio.playbackRate = AppState.speakSlow? 0.7 : 1;
    audio.play();

    $('body').removeClass('audio-loading');
}

function bingSpeak(text, language) {
    if (!text) {
        return;
    }

    var audio = document.getElementById('audio');

    // x. IMPORTANT: Call these after an user trigger event to ensure audio playable on mobile
    audio.src = '';
    audio.play();

    $('body').addClass('audio-loading');

    if (AppState.bingSpeechToken.length > 0) {
        bingSpeakWithToken(text, AppState.bingSpeechToken, language);

        if (AppState.bingSpeechTimeout) {
            clearTimeout(AppState.bingSpeechTimeout)
        }

        AppState.bingSpeechTimeout = setTimeout(function() {
            $.ajax({
                url: "index.php?action=language/getBingSpeechToken&ajax=1",
                type: "GET",
                dataType: 'json',
                success: function(response) {
                    if (response.token) {
                        AppState.bingSpeechToken = response.token;
                    }
                }
            });
        }, 60000)
    } else {
        $.ajax({
            url: "index.php?action=language/getBingSpeechToken&ajax=1",
            type: "GET",
            dataType: 'json',
            success: function(response) {
                if (response.token) {
                    AppState.bingSpeechToken = response.token;
                    bingSpeakWithToken(text, response.token, language);
                }
            }
        });
    }
}

function bingSpeakWithToken(text, token, language) {
    var p = new Object;

    p.appId = "Bearer " + token;
    p.text = text;
    p.language = language;
    p.format = "audio/mp3";
    p.options = "MaxQuality|female";
    //p.options = "MinSize|female";
    p.oncomplete = 'ajaxBingSpeechCallback';
    p.onerror = 'ajaxBingSpeechError';

    $.ajax({
        url: "//api.microsofttranslator.com/V2/Ajax.svc/Speak",
        type: "GET",
        data: p,
        dataType: 'jsonp',
        cache: true
    });
}

///////////////////////////////////////////////////////////////////////////////
// Text selection
///////////////////////////////////////////////////////////////////////////////
var global_current_sentence;

function getSelectionText() {
    var text = ""
    if (window.getSelection) {
        text = window.getSelection().toString()
    } else if (document.selection && document.selection.type != "Control") {
        text = document.selection.createRange().text
    }
    return text
}

function getCurrentSentence() {
    var s = window.getSelection();

    return s.anchorNode.wholeText;
}

function getSelectionText2() {
    var s = window.getSelection();

    s.modify('extend','backward','word');
    var b = s.toString();

    s.modify('extend','forward','word');
    var a = s.toString();

    s.modify('move','forward','character');

    return b + a;
}

function getSelectionText3() {
    var s = window.getSelection()

    s.modify('extend','backward','word')
    var b = s.toString()

    s.modify('extend','forward','word')
    s.modify('extend','forward','word')
    s.modify('extend','forward','word')
    var a = s.toString()

    s.modify('move','forward','character')

    return b + a
}

///////////////////////////////////////////////////////////////////////////////
// Split panes
///////////////////////////////////////////////////////////////////////////////
function initSplitPanes(wrapper, options) {
    const gutters = wrapper.querySelectorAll(".gutter");
    const panes = wrapper.querySelectorAll(".pane");
    const minWidth = options.minWidth || 100
    const minHeight = options.minHeight || 100

    function resizer(e) {
        window.addEventListener("mousemove", mousemove);
        window.addEventListener("mouseup", mouseup);

        //   check gutter if vertical or horizontal
        const is_vertical = e.currentTarget.classList.contains("gutter-v");

        //   get previous position (x or y depending on is_vertical)
        const prev = is_vertical ? e.x : e.y;

        //   get current pane, the parent pane of the gutter you are moving
        const currentPane = e.currentTarget.parentNode;
        const currentPanel = currentPane.getBoundingClientRect();

        //   get previous pane, when move gutter-v:
        //   if current pane is center, prev pane will be left pane
        //   if current pane is right, prev pane will be center pane
        //   left pane will never be current pane cause it don't have gutter
        const prevPane = currentPane.previousElementSibling;
        const prevPanel = prevPane.getBoundingClientRect();

        function mousemove(e) {
            // minWidth and minHeight are string ('200px' and '100px' in this case), change them to integer
            const min = parseInt(is_vertical ? minWidth : minHeight);

            // calculate distance between prev and current position
            const distance = prev - (is_vertical ? e.x : e.y);

            // calculate new width/height of current pane and prev pane
            const newCurrentSize = (is_vertical ? currentPanel.width : currentPanel.height) + distance;
            const newPrevSize = (is_vertical ? prevPanel.width : prevPanel.height) - distance;

            // if new width/height is less than min, return and don't change pane style
            if (newCurrentSize < min || newPrevSize < min) {
                return;
            }

            // change pane width/height depending on is_vertical
            if (is_vertical) {
                currentPane.style.width = newCurrentSize + "px";
                prevPane.style.width = newPrevSize + "px";
            } else {
                currentPane.style.height = newCurrentSize + "px";
                prevPane.style.height = newPrevSize + "px";
            }
        }

        function mouseup() {
            window.removeEventListener("mousemove", mousemove);
            window.removeEventListener("mouseup", mouseup);
        }
    }

    gutters.forEach((gutter) => gutter.addEventListener("mousedown", resizer));
}

///////////////////////////////////////////////////////////////////////////////
// WebSocket
///////////////////////////////////////////////////////////////////////////////
var sock;

const WS_CHANNEL_BUILDER_FILE_MODIFIED  = 'builder::file_modified'

function initFileModifiedWebSocket() {
    sock = new ReconnectingWebSocket('ws://apps4clouds.com:9999/echo/websocket', null, {debug: false, reconnectInterval: 1000})

    sock.onopen = function() {
         sock.send(JSON.stringify({command: 'subscribe', channel: WS_CHANNEL_BUILDER_FILE_MODIFIED, sid: USER_SESSION_ID}))
    }

    sock.onmessage = function(event) {
        console.log('sock message', event)

        var obj = JSON.parse(event.data)

        if (!obj || !obj.success && !obj.channel) {
            return
        }

        let channel = obj.channel
        let data = obj.data

        if (channel == WS_CHANNEL_BUILDER_FILE_MODIFIED) {
            let filename = data.file
            let sid = data.sid

            if (sid != USER_SESSION_ID) {
                editorSetFileAsModified(filename)
            }
        }
    }
}

$(function() {
    // initFileModifiedWebSocket();
})

$(function() {
    var mobileHover = function () {
        $('*').on('touchstart', function () {
            $(this).trigger('hover');
        }).on('touchend', function () {
            $(this).trigger('hover');
        });
    };

    mobileHover();
})

var MobileDetect = {
    Android: function() {
        return navigator.userAgent.match(/Android/i);
    },
    BlackBerry: function() {
        return navigator.userAgent.match(/BlackBerry/i);
    },
    iOS: function() {
        return navigator.userAgent.match(/iPhone|iPad|iPod/i);
    },
    Opera: function() {
        return navigator.userAgent.match(/Opera Mini/i);
    },
    Windows: function() {
        return navigator.userAgent.match(/IEMobile/i);
    },
    any: function() {
        return (this.Android() || this.BlackBerry() || this.iOS() || this.Opera() || this.Windows());
    }
};

$(function() {
    if (MobileDetect.any()) {
        $('html').addClass('mobile')
    }

    if (MobileDetect.iOS()) {
        $('html').addClass('ios')
    }

    if (MobileDetect.Android()) {
        $('html').addClass('android')
    }
})
