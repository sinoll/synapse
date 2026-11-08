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