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
