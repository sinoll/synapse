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
