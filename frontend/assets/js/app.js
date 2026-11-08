/* frontend\assets\favorite\js\favorite.js */
(function () {
    'use strict';

    const csrfParam = (document.querySelector('meta[name="csrf-param"]') || {}).content || '';
    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const paint = function (button, favorited) {
        button.classList.toggle('is-favorited', favorited);
        button.setAttribute('aria-pressed', favorited ? 'true' : 'false');

        const title = favorited ? button.dataset.labelOn : button.dataset.labelOff;
        button.title = title;
        button.setAttribute('aria-label', title);

        const icon = button.querySelector('i');
        if (icon) icon.className = 'bi ' + (favorited ? 'bi-star-fill' : 'bi-star');

        const label = button.querySelector('[data-favorite-label]');
        if (label) label.textContent = title;
    };

    const flashError = function (button) {
        button.classList.add('has-error');
        window.setTimeout(function () { button.classList.remove('has-error'); }, 600);
    };

    // 「我的收藏」表格里取消收藏，整行删掉；行清空后连表格一起收起，只留提示。
    const dropFromFavoriteList = function (button) {
        const list = button.closest('[data-favorite-list]');
        if (!list) return;

        const row = button.closest('tr');
        if (row) row.remove();

        const rest = list.querySelectorAll('tbody tr').length;
        list.classList.toggle('d-none', rest === 0);

        const empty = document.querySelector('.favorite-list-empty');
        if (empty) empty.classList.toggle('d-none', rest > 0);
    };

    // 事件委托：无限滚动追加进来的片段里的按钮同样生效，不需要再次绑定。
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.favorite-button[data-favorite-post]');
        if (!button || button.classList.contains('is-busy')) return;

        event.preventDefault();

        const payload = new URLSearchParams();
        payload.set('post_id', button.dataset.favoritePost);
        if (csrfParam) payload.set(csrfParam, csrfToken);

        button.classList.add('is-busy');

        fetch(button.dataset.favoriteUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: payload.toString(),
        }).then(function (response) {
            if (response.status === 401) {
                const loginUrl = button.dataset.loginUrl || '/users/login';
                window.location.assign(loginUrl + '?returnUrl=' + encodeURIComponent(window.location.pathname + window.location.search));
                throw new Error('login required');
            }
            if (!response.ok) throw new Error('favorite request failed');

            return response.json();
        }).then(function (result) {
            paint(button, Boolean(result.favorited));
            if (!result.favorited) dropFromFavoriteList(button);
        }).catch(flashError).finally(function () {
            button.classList.remove('is-busy');
        });
    });
}());


/* frontend\assets\follow\js\follow.js */
(function () {
    'use strict';

    const csrfParam = (document.querySelector('meta[name="csrf-param"]') || {}).content || '';
    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const paint = function (button, followed) {
        button.classList.toggle('is-followed', followed);
        button.setAttribute('aria-pressed', followed ? 'true' : 'false');

        const title = followed ? '取消关注' : '关注';
        button.title = title;
        button.setAttribute('aria-label', title);

        const icon = button.querySelector('i');
        if (icon) icon.className = 'bi ' + (followed ? 'bi-check2' : 'bi-person-plus');

        const label = button.querySelector('[data-follow-label]');
        if (label) label.textContent = followed ? button.dataset.labelOn : button.dataset.labelOff;
    };

    // 同一作者可能在一页出现多处（详情页头部、作者卡片、评论区），全部同步刷新。
    const repaintAuthor = function (userId, followed, count) {
        document.querySelectorAll('.follow-button[data-follow-user="' + userId + '"]').forEach(function (button) {
            paint(button, followed);
        });
        if (count === undefined || count === null) return;
        document.querySelectorAll('[data-follow-count-for="' + userId + '"]').forEach(function (counter) {
            const full = parseInt(counter.dataset.followFull || '0', 10) + (followed ? 1 : -1);
            counter.dataset.followFull = String(Math.max(0, full));
            counter.textContent = full >= 10000 ? (Math.round(full / 1000) / 10) + '万' : String(Math.max(0, full));
        });
    };

    const flashError = function (button) {
        button.classList.add('has-error');
        window.setTimeout(function () { button.classList.remove('has-error'); }, 600);
    };

    // 事件委托：无限滚动追加进来的片段里的按钮同样生效，不需要再次绑定。
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.follow-button[data-follow-user]');
        if (!button || button.classList.contains('is-busy')) return;

        event.preventDefault();

        const payload = new URLSearchParams();
        payload.set('user_id', button.dataset.followUser);
        if (csrfParam) payload.set(csrfParam, csrfToken);

        button.classList.add('is-busy');

        fetch(button.dataset.followUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: payload.toString(),
        }).then(function (response) {
            if (response.status === 401) {
                const loginUrl = button.dataset.loginUrl || '/users/login';
                window.location.assign(loginUrl + '?returnUrl=' + encodeURIComponent(window.location.pathname + window.location.search));
                throw new Error('login required');
            }
            if (!response.ok) throw new Error('follow request failed');

            return response.json();
        }).then(function (result) {
            repaintAuthor(result.user_id, Boolean(result.followed), result.count);
        }).catch(function () {
            flashError(button);
        }).finally(function () {
            button.classList.remove('is-busy');
        });
    });
}());


/* frontend\assets\imageviewer\js\imageviewer.js */
(function () {
    'use strict';

    const TRIGGER = 'a[data-image-view]';

    let viewer = null;
    let stage = null;
    let image = null;
    let counter = null;
    let caption = null;
    let thumbBar = null;
    let prevButton = null;
    let nextButton = null;
    let items = [];
    let index = 0;
    let trigger = null;
    let scrollGap = 0;

    const tag = function (tagName, className) {
        const node = document.createElement(tagName);
        if (className) node.className = className;
        return node;
    };

    const arrowButton = function (className, icon, label) {
        const button = tag('button', 'image-viewer-arrow ' + className);
        button.type = 'button';
        button.setAttribute('aria-label', label);
        const glyph = tag('i', 'bi ' + icon);
        glyph.setAttribute('aria-hidden', 'true');
        button.append(glyph);
        return button;
    };

    /** href 就是大图地址：禁用 JS 时点击直接打开原图，脚本接管后放进看图遮罩。 */
    const fullSource = function (node) {
        return node.getAttribute('href');
    };

    const thumbSource = function (node) {
        const inner = node.querySelector('img');
        return inner ? inner.getAttribute('src') : fullSource(node);
    };

    const build = function () {
        viewer = tag('div', 'image-viewer');
        viewer.setAttribute('role', 'dialog');
        viewer.setAttribute('aria-modal', 'true');
        viewer.setAttribute('aria-label', '图片查看');
        viewer.setAttribute('tabindex', '-1');

        const bar = tag('div', 'image-viewer-bar');
        counter = tag('span', 'image-viewer-counter');
        const closeButton = tag('button', 'image-viewer-tool');
        closeButton.type = 'button';
        closeButton.setAttribute('aria-label', '关闭（Esc）');
        closeButton.append(glyph('bi-x-lg'));
        bar.append(counter, closeButton);

        prevButton = arrowButton('image-viewer-prev', 'bi-chevron-left', '上一张（←）');
        nextButton = arrowButton('image-viewer-next', 'bi-chevron-right', '下一张（→）');

        stage = tag('div', 'image-viewer-stage');
        const spinner = tag('span', 'image-viewer-spinner');
        spinner.setAttribute('aria-hidden', 'true');
        image = tag('img', 'image-viewer-img');
        stage.append(spinner, image);

        const foot = tag('div', 'image-viewer-foot');
        caption = tag('p', 'image-viewer-caption');
        thumbBar = tag('div', 'image-viewer-thumbs');
        foot.append(caption, thumbBar);

        viewer.append(bar, prevButton, stage, nextButton, foot);
        document.body.append(viewer);

        closeButton.addEventListener('click', hide);
        prevButton.addEventListener('click', function () { step(-1); });
        nextButton.addEventListener('click', function () { step(1); });

        viewer.addEventListener('click', function (event) {
            if (event.target === viewer || event.target === stage) hide();
        });
        image.addEventListener('click', function (event) {
            event.stopPropagation();
            if (panMoved) {
                panMoved = false;
                return;
            }
            stage.classList.toggle('is-zoomed');
            if (!stage.classList.contains('is-zoomed')) stage.scrollTo({left: 0, top: 0});
        });

        stage.addEventListener('pointerdown', startPan);
        document.addEventListener('keydown', onKeydown);
    };

    const glyph = function (name) {
        const icon = tag('i', 'bi ' + name);
        icon.setAttribute('aria-hidden', 'true');
        return icon;
    };

    /** 放大后拖动作平移；拖动过就吞掉紧随其后的点击，否则平移一次就把缩放关掉了。 */
    let panState = null;
    let panMoved = false;

    const startPan = function (event) {
        if (!stage.classList.contains('is-zoomed') || event.target !== image) return;
        panState = {x: event.clientX, y: event.clientY, left: stage.scrollLeft, top: stage.scrollTop};
        panMoved = false;
        stage.classList.add('is-panning');
        event.preventDefault();
    };

    const movePan = function (event) {
        if (!panState) return;
        const dx = event.clientX - panState.x;
        const dy = event.clientY - panState.y;
        if (!panMoved && Math.abs(dx) + Math.abs(dy) < 4) return;
        panMoved = true;
        stage.scrollLeft = panState.left - dx;
        stage.scrollTop = panState.top - dy;
    };

    const endPan = function () {
        if (!panState) return;
        panState = null;
        stage.classList.remove('is-panning');
    };

    const collect = function (node) {
        const group = node.dataset.imageGroup;
        if (!group) return [node];

        return Array.prototype.slice.call(
            document.querySelectorAll(TRIGGER + '[data-image-group="' + group + '"]'),
        );
    };

    const buildThumbs = function () {
        thumbBar.textContent = '';
        if (items.length < 2) return;

        items.forEach(function (node, position) {
            const thumb = tag('button', 'image-viewer-thumb');
            thumb.type = 'button';
            thumb.setAttribute('aria-label', '第 ' + (position + 1) + ' 张');
            const picture = tag('img');
            picture.src = thumbSource(node) || '';
            picture.alt = '';
            picture.loading = 'lazy';
            thumb.append(picture);
            thumb.addEventListener('click', function () { go(position); });
            thumbBar.append(thumb);
        });
    };

    const paint = function () {
        const node = items[index];

        viewer.classList.add('is-loading');
        image.onload = function () { viewer.classList.remove('is-loading'); };
        image.onerror = function () { viewer.classList.remove('is-loading'); };
        image.src = fullSource(node);
        image.alt = node.dataset.imageCaption || '';

        counter.textContent = (index + 1) + ' / ' + items.length;
        caption.textContent = node.dataset.imageCaption || '';
        viewer.classList.toggle('is-single', items.length < 2);

        stage.classList.remove('is-zoomed', 'is-panning');
        viewer.querySelectorAll('.image-viewer-thumb').forEach(function (thumb, position) {
            thumb.classList.toggle('is-active', position === index);
            if (position === index) thumb.scrollIntoView({block: 'nearest', inline: 'nearest'});
        });

        [items[index + 1], items[index - 1]].forEach(function (neighbour) {
            if (!neighbour) return;
            const preloader = new Image();
            preloader.src = fullSource(neighbour);
        });
    };

    const go = function (position) {
        index = (position + items.length) % items.length;
        paint();
    };

    const step = function (delta) {
        go(index + delta);
    };

    const lockScroll = function () {
        const root = document.documentElement;
        scrollGap = window.innerWidth - root.clientWidth;
        root.style.overflow = 'hidden';
        if (scrollGap > 0) document.body.style.paddingRight = scrollGap + 'px';
    };

    const unlockScroll = function () {
        document.documentElement.style.overflow = '';
        document.body.style.paddingRight = '';
    };

    const show = function (node) {
        if (!viewer) build();

        items = collect(node);
        index = items.indexOf(node);
        if (index < 0) index = 0;
        trigger = node;

        buildThumbs();
        viewer.hidden = false;
        paint();
        lockScroll();
        stage.addEventListener('pointermove', movePan);
        stage.addEventListener('pointerup', endPan);
        stage.addEventListener('pointercancel', endPan);
        viewer.focus({preventScroll: true});
    };

    const hide = function () {
        if (!viewer || viewer.hidden) return;

        viewer.hidden = true;
        image.removeAttribute('src');
        stage.removeEventListener('pointermove', movePan);
        stage.removeEventListener('pointerup', endPan);
        stage.removeEventListener('pointercancel', endPan);
        unlockScroll();
        if (trigger) trigger.focus({preventScroll: true});
    };

    const onKeydown = function (event) {
        if (!viewer || viewer.hidden) return;

        switch (event.key) {
            case 'Escape':
                event.preventDefault();
                hide();
                break;
            case 'ArrowLeft':
                event.preventDefault();
                step(-1);
                break;
            case 'ArrowRight':
                event.preventDefault();
                step(1);
                break;
        }
    };

    document.addEventListener('click', function (event) {
        const node = event.target.closest(TRIGGER);
        if (!node) return;

        event.preventDefault();
        show(node);
    });
}());


/* frontend\assets\like\js\like.js */
(function () {
    'use strict';

    const csrfParam = (document.querySelector('meta[name="csrf-param"]') || {}).content || '';
    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const paint = function (button, liked, count) {
        button.classList.toggle('is-liked', liked);
        button.setAttribute('aria-pressed', liked ? 'true' : 'false');

        const title = liked ? '取消点赞' : '点赞';
        button.title = title;
        button.setAttribute('aria-label', title);

        const icon = button.querySelector('i');
        if (icon) icon.className = 'bi ' + (liked ? 'bi-heart-fill' : 'bi-heart');

        const label = button.querySelector('[data-like-label]');
        if (label) label.textContent = liked ? button.dataset.labelOn : button.dataset.labelOff;

        const counter = button.querySelector('[data-like-count]');
        if (counter && count !== undefined && count !== null) counter.textContent = String(count);
    };

    const flashError = function (button) {
        button.classList.add('has-error');
        window.setTimeout(function () { button.classList.remove('has-error'); }, 600);
    };

    // 「我的点赞」表格里取消点赞，整行删掉；行清空后连表格一起收起，只留提示。
    const dropFromLikeList = function (button) {
        const list = button.closest('[data-like-list]');
        if (!list) return;

        const row = button.closest('tr');
        if (row) row.remove();

        const rest = list.querySelectorAll('tbody tr').length;
        list.classList.toggle('d-none', rest === 0);

        const empty = document.querySelector('.like-list-empty');
        if (empty) empty.classList.toggle('d-none', rest > 0);
    };

    // 事件委托：无限滚动追加进来的片段里的按钮同样生效，不需要再次绑定。
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.like-button[data-like-post]');
        if (!button || button.classList.contains('is-busy')) return;

        event.preventDefault();

        const payload = new URLSearchParams();
        payload.set('post_id', button.dataset.likePost);
        if (csrfParam) payload.set(csrfParam, csrfToken);

        button.classList.add('is-busy');

        fetch(button.dataset.likeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: payload.toString(),
        }).then(function (response) {
            if (response.status === 401) {
                const loginUrl = button.dataset.loginUrl || '/users/login';
                window.location.assign(loginUrl + '?returnUrl=' + encodeURIComponent(window.location.pathname + window.location.search));
                throw new Error('login required');
            }
            if (!response.ok) throw new Error('like request failed');

            return response.json();
        }).then(function (result) {
            paint(button, Boolean(result.liked), result.count);
            if (!result.liked) dropFromLikeList(button);
        }).catch(flashError).finally(function () {
            button.classList.remove('is-busy');
        });
    });
}());


/* frontend\assets\mention\js\mention.js */
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

/* frontend\assets\momentcollapse\js\momentcollapse.js */
(function () {
    'use strict';

    // 折叠后还剩这么多像素才算「真的被截掉」，避免差一两个像素冒出没用的开关。
    const OVERFLOW_PX = 4;

    const toggleOf = function (block) { return block.querySelector('.home-feed-toggle'); };

    const sync = function (block) {
        const toggle = toggleOf(block);
        if (!toggle) return;

        const expanded = block.classList.contains('is-expanded');
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.classList.toggle('is-expanded', expanded);

        const label = toggle.querySelector('.home-feed-toggle-label');
        if (label) label.textContent = expanded ? '收起' : '展开';
    };

    // 先套上折叠样式量一次（is-measuring），确实被截掉才挂 is-collapsible 并显示开关。
    const measure = function (block) {
        const text = block.querySelector('.home-feed-collapse-text');
        if (!text) return;

        const expanded = block.classList.contains('is-expanded');
        block.classList.remove('is-expanded', 'is-collapsible');
        block.classList.add('is-measuring');

        const overflow = text.scrollHeight - text.clientHeight > OVERFLOW_PX;

        block.classList.remove('is-measuring');
        block.classList.toggle('is-collapsible', overflow);
        if (overflow && expanded) block.classList.add('is-expanded');

        sync(block);
    };

    const measureAll = function () {
        document.querySelectorAll('.home-feed-collapse').forEach(measure);
    };

    // 事件委托：无限滚动追加进来的卡片不用重新绑定点击。
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('.home-feed-toggle');
        if (!toggle) return;

        const block = toggle.closest('.home-feed-collapse');
        if (!block) return;

        block.classList.toggle('is-expanded');
        sync(block);
    });

    let resizeTimer = 0;
    const onResize = function () {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(measureAll, 200);
    };

    const start = function () {
        measureAll();

        // 追加进来的新卡片当场测量，省得再暴露一个初始化函数给翻页脚本调用。
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                record.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.classList && node.classList.contains('home-feed-collapse')) measure(node);
                    node.querySelectorAll('.home-feed-collapse').forEach(measure);
                });
            });
        }).observe(document.body, {childList: true, subtree: true});

        window.addEventListener('resize', onResize, {passive: true});
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(measureAll);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());


/* frontend\assets\share\js\share.js */
(function () {
    'use strict';

    const flash = function (button) {
        const icon = button.querySelector('i');
        const label = button.querySelector('[data-share-label]');
        const originalTitle = button.title;
        const originalLabel = label ? label.textContent : '';

        button.classList.add('is-copied');
        if (icon) icon.className = 'bi bi-check-lg';
        if (label) label.textContent = '已复制';
        button.title = '链接已复制';

        window.setTimeout(function () {
            button.classList.remove('is-copied');
            if (icon) icon.className = 'bi bi-share';
            if (label) label.textContent = originalLabel;
            button.title = originalTitle;
        }, 1600);
    };

    // http 局域网地址没有 navigator.clipboard 权限，保留 execCommand 降级。
    const legacyCopy = function (text) {
        const helper = document.createElement('textarea');
        helper.value = text;
        helper.setAttribute('readonly', '');
        helper.style.position = 'fixed';
        helper.style.opacity = '0';
        document.body.appendChild(helper);
        helper.select();
        let copied = false;
        try { copied = document.execCommand('copy'); } catch (error) {}
        helper.remove();
        return copied;
    };

    // 事件委托：无限滚动追加进来的片段里的按钮同样生效，不需要再次绑定。
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.share-button[data-share-url]');
        if (!button) return;

        event.preventDefault();
        const url = button.dataset.shareUrl;

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(function () { flash(button); }).catch(function () {
                if (legacyCopy(url)) flash(button);
            });
            return;
        }
        if (legacyCopy(url)) flash(button);
    });
}());
