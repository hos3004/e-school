{{-- طبقة النوافذ المنبثقة: تُركّب مرة واحدة في كل واجهة --}}
{{-- الفحص والتتبع على الخادم؛ هذا الملف عرض وتفاعل فقط --}}

@php
    $csrf = csrf_token();
@endphp

<div
    id="popup-layer"
    data-authed="{{ auth()->check() ? '1' : '0' }}"
    data-csrf="{{ $csrf }}"
    data-endpoints="{{ json_encode([
        'active' => route('popups.active'),
        'interact' => route('popups.interact', ['campaign' => ':id', 'interaction' => ':type']),
    ]) }}"
></div>

<script>
    (function () {
        var layer = document.getElementById('popup-layer');
        if (!layer || layer.dataset.authed !== '1' || window.__popupLayerBooted) return;
        window.__popupLayerBooted = true;

        var endpoints = JSON.parse(layer.dataset.endpoints);
        var csrf = layer.dataset.csrf;
        var shownCampaignId = null;
        var lastFocus = null;

        function placementForNow() {
            // بعد الدخول: أول طلب في هذه الجلسة يحمل علامة after_login.
            if (!sessionStorage.getItem('popup.after_login_done')) {
                sessionStorage.setItem('popup.after_login_done', '1');
                return { placement: 'after_login', page_key: '' };
            }

            var path = window.location.pathname;
            if (path.indexOf('/admin') === 0) return { placement: 'dashboard', page_key: '' };

            var map = [
                ['/student/schedule', 'student.schedule'],
                ['/student', 'student.dashboard'],
                ['/guardian', 'guardian.dashboard'],
                ['/teacher', 'teacher.dashboard'],
            ];
            for (var i = 0; i < map.length; i++) {
                if (path.indexOf(map[i][0]) === 0) return { placement: 'dashboard', page_key: map[i][1] };
            }

            return { placement: 'all_authenticated_pages', page_key: '' };
        }

        function interactUrl(id, type) {
            return endpoints.interact.replace(':id', encodeURIComponent(id)).replace(':type', type);
        }

        function post(url) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: '{}',
            });
        }

        function show(popup, html) {
            if (document.querySelector('[data-popup-card]')) return; // لا نوافذ متراكمة

            lastFocus = document.activeElement;
            shownCampaignId = popup.id;

            var overlay = document.createElement('div');
            overlay.id = 'popup-overlay';
            overlay.setAttribute('dir', document.documentElement.getAttribute('dir') || 'rtl');
            overlay.style.cssText = 'position:fixed;inset:0;z-index:200;display:flex;align-items:flex-start;' +
                'justify-content:center;padding:4rem 1rem;background:rgba(0,0,0,.35);';
            overlay.innerHTML = html;

            document.body.appendChild(overlay);

            var card = overlay.querySelector('[data-popup-card]');
            if (!card) return;

            card.setAttribute('tabindex', '-1');
            card.focus({ preventScroll: true });

            post(interactUrl(popup.id, 'impression'));

            overlay.addEventListener('click', function (event) {
                var actionEl = event.target.closest('[data-popup-action]');
                var action = actionEl ? actionEl.getAttribute('data-popup-action') : null;

                if (action && !actionEl.disabled) {
                    post(interactUrl(popup.id, action));
                }

                // الإغلاق المتعمد فقط (زر X أو زر الإقرار أو CTA). الضغط خارج
                // النافذة لا يغلق شيئًا يتطلب إقرارا، ولا يوقف الحملات غير القابلة للإغلاق.
                var canClose = popup.is_dismissible || popup.requires_acknowledgement;
                var deliberateClose = action === 'dismiss'
                    || (action === 'acknowledge')
                    || (action === 'click');

                if ((canClose && deliberateClose) || action === 'acknowledge' || action === 'click') {
                    close();
                }
            });

            document.addEventListener('keydown', escHandler);
        }

        function escHandler(event) {
            var card = document.querySelector('[data-popup-card]');
            if (!card) return;
            var dismissible = card.querySelector('[data-popup-action="dismiss"]') !== null;
            var acknowledgedOnly = card.querySelector('[data-popup-action="acknowledge"]') !== null;

            // Escape يغلق فقط ما هو قابل للإغلاق ولا يتطلب إقرارا.
            if (event.key === 'Escape' && dismissible && !acknowledgedOnly) {
                post(interactUrl(shownCampaignId, 'dismiss'));
                close();
            }
        }

        function close() {
            var overlay = document.getElementById('popup-overlay');
            if (overlay) overlay.remove();
            document.removeEventListener('keydown', escHandler);

            if (lastFocus && typeof lastFocus.focus === 'function') {
                lastFocus.focus({ preventScroll: true });
            }

            shownCampaignId = null;
        }

        // إعادة التحقق عند تنقل Inertia/SPA دون إعادة تحميل كاملة.
        function check() {
            if (document.querySelector('[data-popup-card]')) return; // واحدة في الوقت نفسه

            fetch(endpoints.active + '?' + new URLSearchParams(placementForNow()), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (payload) {
                    if (payload && payload.popup && payload.html) show(payload.popup, payload.html);
                })
                .catch(function () {});
        }

        var lastPath = window.location.pathname;
        setInterval(function () {
            if (window.location.pathname !== lastPath) {
                lastPath = window.location.pathname;
                sessionStorage.setItem('popup.after_login_done', '1');
                setTimeout(check, 600);
            }
        }, 500);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', check);
        } else {
            setTimeout(check, 400);
        }
    })();
</script>
