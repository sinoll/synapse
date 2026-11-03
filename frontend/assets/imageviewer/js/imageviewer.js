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
