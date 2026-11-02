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
