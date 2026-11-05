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
