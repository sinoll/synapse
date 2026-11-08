(function () {
    'use strict';

    // @ 后允许空查询（刚敲下 @ 就出候选），名称字符与后端 Mention 解析保持一致。
    const QUERY_RE = /@([\p{L}\p{N}_-]{0,40})$/u;
    const BOUNDARY_RE = /[\p{L}\p{N}_@]/u;

    let dropdown = null;
    let state = null;

    const ensureDropdown = function () {
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.className = 'mention-dropdown d-none';
            dropdown.setAttribute('role', 'listbox');
            dropdown.setAttribute('aria-label', '提及候选用户');
            document.body.appendChild(dropdown);
            dropdown.addEventListener('mousedown', function (event) {
                // 阻止默认行为保住输入框焦点，否则 blur 会先关掉下拉。
                event.preventDefault();
                const option = event.target.closest('.mention-option');
                if (option) pick(Number(option.dataset.index));
            });
        }
        return dropdown;
    };

    const close = function () {
        if (state) {
            window.clearTimeout(state.timer);
            if (state.controller) state.controller.abort();
        }
        state = null;
        if (dropdown) dropdown.classList.add('d-none');
    };

    /** 光标前的文本是否正处于 @查询 状态；返回替换所需的定位信息。 */
    const contextOf = function (input) {
        if (input.tagName === 'TEXTAREA' || input.tagName === 'INPUT') {
            const caret = input.selectionStart;
            if (caret === null || input.selectionEnd !== caret) return null;
            const before = input.value.slice(0, caret);
            const match = before.match(QUERY_RE);
            if (!match) return null;
            const start = caret - match[0].length;
            if (start > 0 && BOUNDARY_RE.test(before.charAt(start - 1))) return null;
            return { kind: 'field', query: match[1], start: start, caret: caret };
        }

        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0 || !selection.isCollapsed) return null;
        const node = selection.anchorNode;
        if (!node || node.nodeType !== Node.TEXT_NODE || !input.contains(node)) return null;
        const before = node.textContent.slice(0, selection.anchorOffset);
        const match = before.match(QUERY_RE);
        if (!match) return null;
        const start = selection.anchorOffset - match[0].length;
        if (start > 0 && BOUNDARY_RE.test(before.charAt(start - 1))) return null;
        return { kind: 'editable', query: match[1], node: node, start: start, caret: selection.anchorOffset };
    };

    /** textarea 没有现成的光标坐标 API，用镜像 div 排版同一段文字来量。 */
    const fieldCaretPoint = function (input, caret) {
        const style = window.getComputedStyle(input);
        const mirror = document.createElement('div');
        ['boxSizing', 'width', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
            'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth',
            'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textIndent',
            'textTransform'].forEach(function (prop) { mirror.style[prop] = style[prop]; });
        mirror.style.position = 'absolute';
        mirror.style.top = '0';
        mirror.style.left = '-9999px';
        mirror.style.visibility = 'hidden';
        mirror.style.whiteSpace = 'pre-wrap';
        mirror.style.wordWrap = 'break-word';
        mirror.textContent = input.value.slice(0, caret);
        const marker = document.createElement('span');
        marker.textContent = '\u200b';
        mirror.appendChild(marker);
        document.body.appendChild(mirror);
        const box = input.getBoundingClientRect();
        const lineHeight = parseFloat(style.lineHeight) || parseFloat(style.fontSize) * 1.5;
        const point = {
            left: box.left + marker.offsetLeft,
            top: box.top + marker.offsetTop + lineHeight - input.scrollTop,
        };
        mirror.remove();
        return point;
    };

    const caretPoint = function (input, context) {
        if (context.kind === 'editable') {
            const range = document.createRange();
            range.setStart(context.node, context.caret);
            range.collapse(true);
            const rect = range.getBoundingClientRect();
            if (rect.top !== 0 || rect.left !== 0) {
                return { left: rect.left, top: rect.bottom };
            }
        } else {
            return fieldCaretPoint(input, context.caret);
        }
        const box = input.getBoundingClientRect();
        return { left: box.left, top: box.bottom };
    };

    const paintActive = function () {
        if (!dropdown || !state) return;
        dropdown.querySelectorAll('.mention-option').forEach(function (option, index) {
            const active = index === state.index;
            option.classList.toggle('is-active', active);
            option.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) option.scrollIntoView({ block: 'nearest' });
        });
    };

    const show = function (input, context, users) {
        const menu = ensureDropdown();
        menu.replaceChildren();
        users.forEach(function (user, index) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'mention-option';
            option.dataset.index = String(index);
            option.setAttribute('role', 'option');
            const name = document.createElement('span');
            name.className = 'mention-option-name';
            name.textContent = user.name;
            const handle = document.createElement('span');
            handle.className = 'mention-option-handle';
            handle.textContent = '@' + user.username;
            option.append(name, handle);
            menu.appendChild(option);
        });
        state.items = users;
        state.index = 0;
        menu.classList.remove('d-none');
        paintActive();

        const point = caretPoint(input, context);
        const menuWidth = Math.min(300, window.innerWidth - 16);
        menu.style.width = menuWidth + 'px';
        menu.style.left = Math.max(8, Math.min(point.left, window.innerWidth - menuWidth - 8)) + 'px';
        menu.style.top = '0px';
        const menuHeight = menu.offsetHeight;
        let top = point.top + 6;
        if (top + menuHeight > window.innerHeight - 8) {
            top = Math.max(8, point.top - menuHeight - 24);
        }
        menu.style.top = top + 'px';
    };

    const requestUsers = function (input, context) {
        const url = input.dataset.mentionUrl;
        if (!url || !state) return;
        if (state.controller) state.controller.abort();
        const controller = new AbortController();
        state.controller = controller;
        const query = context.query;

        fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(query), {
            credentials: 'same-origin',
            signal: controller.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        }).then(function (response) {
            if (!response.ok) throw new Error('mention suggest failed');
            return response.json();
        }).then(function (result) {
            if (!state || state.input !== input) return;
            const fresh = contextOf(input);
            if (!fresh || fresh.query !== query) return;
            state.context = fresh;
            const users = (result.users || []).slice(0, 8);
            if (users.length === 0) {
                close();
                return;
            }
            show(input, fresh, users);
        }).catch(function (error) {
            if (error.name !== 'AbortError') close();
        });
    };

    const detect = function (input) {
        const context = contextOf(input);
        if (!context) {
            close();
            return;
        }
        if (!state || state.input !== input) {
            close();
            state = { input: input, timer: 0, controller: null, items: [], index: 0 };
        }
        state.context = context;
        window.clearTimeout(state.timer);
        state.timer = window.setTimeout(function () {
            if (state && state.input === input) requestUsers(input, context);
        }, 180);
    };

    /** 选中候选：插入唯一的 @username（而非可能重名的昵称），渲染侧两者都能解析。 */
    const pick = function (index) {
        if (!state || !state.items[index]) return;
        const input = state.input;
        const user = state.items[index];
        const context = contextOf(input);
        if (!context) {
            close();
            return;
        }
        const insert = '@' + user.username + ' ';
        if (context.kind === 'field') {
            input.setRangeText(insert, context.start, context.caret, 'end');
        } else {
            const node = context.node;
            const text = node.textContent;
            node.textContent = text.slice(0, context.start) + insert + text.slice(context.caret);
            const selection = window.getSelection();
            const range = document.createRange();
            range.setStart(node, context.start + insert.length);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        }
        close();
        input.focus();
        // 通知发布器等监听 input 的脚本同步隐藏 textarea。
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };

    document.addEventListener('input', function (event) {
        const target = event.target;
        if (!target || !target.closest) return;
        const input = target.closest('[data-mention]');
        if (input) detect(input);
    });

    document.addEventListener('keydown', function (event) {
        if (!state || !dropdown || dropdown.classList.contains('d-none')) return;
        if (event.target !== state.input && !state.input.contains(event.target)) return;

        if (event.key === 'ArrowDown' && state.items.length) {
            event.preventDefault();
            state.index = (state.index + 1) % state.items.length;
            paintActive();
        } else if (event.key === 'ArrowUp' && state.items.length) {
            event.preventDefault();
            state.index = (state.index - 1 + state.items.length) % state.items.length;
            paintActive();
        } else if ((event.key === 'Enter' || event.key === 'Tab') && state.items.length) {
            event.preventDefault();
            pick(state.index);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    }, true);

    document.addEventListener('click', function (event) {
        if (!state) return;
        if (dropdown && dropdown.contains(event.target)) return;
        if (event.target === state.input || state.input.contains(event.target)) {
            detect(state.input);
            return;
        }
        close();
    });

    document.addEventListener('focusout', function (event) {
        if (!state || event.target !== state.input) return;
        window.setTimeout(function () {
            if (state && document.activeElement !== state.input) close();
        }, 120);
    });

    window.addEventListener('scroll', function () {
        if (state) close();
    }, true);
}());