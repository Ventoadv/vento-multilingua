/* Vento Multilingua - editor visuale delle traduzioni */
(function () {
    var cfg = window.vmlEditor;
    if (!cfg) return;
    var t = cfg.i18n;
    var strings = cfg.strings || {};

    function el(tag, attrs, html) {
        var e = document.createElement(tag);
        for (var k in (attrs || {})) e.setAttribute(k, attrs[k]);
        if (html !== undefined) e.innerHTML = html;
        return e;
    }
    function norm(s) { return (s || '').replace(/[\s ]+/g, ' ').trim(); }
    // Le traduzioni non distinguono maiuscole/minuscole: trova la chiave salvata equivalente.
    function findKey(text) {
        var f = norm(text).toLowerCase();
        for (var k in strings) { if (Object.prototype.hasOwnProperty.call(strings, k) && k.toLowerCase() === f) return k; }
        return null;
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Barra in basso
    var bar = el('div', { id: 'vml-ed-bar', class: 'notranslate', translate: 'no' },
        '<strong>✏️ ' + esc(t.bar) + ' · ' + esc(cfg.langName) + '</strong><span>' + esc(t.hint) + '</span>' +
        '<a href="' + esc(cfg.exitUrl) + '" class="vml-ed-btn">' + esc(t.exit) + '</a>');
    document.body.appendChild(bar);
    document.documentElement.classList.add('vml-editing');

    // Modale
    var overlay = el('div', { id: 'vml-ed-overlay', class: 'notranslate', translate: 'no' });
    overlay.innerHTML =
        '<div class="vml-ed-modal" role="dialog" aria-modal="true">' +
        '<label>' + esc(t.original) + '</label><div class="vml-ed-orig"></div>' +
        '<label for="vml-ed-trans">' + esc(t.translation) + ' (' + esc(cfg.langName) + ')</label>' +
        '<textarea id="vml-ed-trans" rows="4"></textarea>' +
        '<div class="vml-ed-actions"><button type="button" class="vml-ed-del">' + esc(t['delete']) + '</button>' +
        '<span class="vml-ed-msg"></span>' +
        '<button type="button" class="vml-ed-cancel">' + esc(t.cancel) + '</button>' +
        '<button type="button" class="vml-ed-save">' + esc(t.save) + '</button></div></div>';
    document.body.appendChild(overlay);
    var origBox = overlay.querySelector('.vml-ed-orig');
    var ta = overlay.querySelector('textarea');
    var msg = overlay.querySelector('.vml-ed-msg');
    var delBtn = overlay.querySelector('.vml-ed-del');
    var current = '';

    function open(text) {
        current = norm(text);
        origBox.textContent = current;
        var key = findKey(current);
        ta.value = key ? strings[key] : '';
        delBtn.style.display = key ? '' : 'none';
        msg.textContent = '';
        overlay.classList.add('vml-show');
        setTimeout(function () { ta.focus(); }, 30);
    }
    function close() { overlay.classList.remove('vml-show'); }

    function post(action, data) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', cfg.nonce);
        body.append('lang', cfg.lang);
        for (var k in data) body.append(k, data[k]);
        return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); });
    }

    overlay.querySelector('.vml-ed-cancel').addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    overlay.querySelector('.vml-ed-save').addEventListener('click', function () {
        var val = norm(ta.value);
        if (!val) { ta.focus(); return; }
        msg.textContent = t.saving;
        post('vml_save_string', { original: current, translation: val }).then(function (r) {
            if (r && r.success) { location.reload(); } else { msg.textContent = t.error; }
        })['catch'](function () { msg.textContent = t.error; });
    });
    delBtn.addEventListener('click', function () {
        msg.textContent = t.saving;
        post('vml_delete_string', { original: current }).then(function (r) {
            if (r && r.success) { location.reload(); } else { msg.textContent = t.error; }
        })['catch'](function () { msg.textContent = t.error; });
    });

    function textAtPoint(x, y) {
        var node = null;
        if (document.caretPositionFromPoint) {
            var p = document.caretPositionFromPoint(x, y);
            node = p && p.offsetNode;
        } else if (document.caretRangeFromPoint) {
            var r = document.caretRangeFromPoint(x, y);
            node = r && r.startContainer;
        }
        if (node && node.nodeType === 3 && norm(node.nodeValue)) return node.nodeValue;
        return '';
    }

    // Intercetta i click sulla pagina
    document.addEventListener('click', function (e) {
        if (e.target.closest('#vml-ed-bar, #vml-ed-overlay, #wpadminbar')) return;
        e.preventDefault();
        e.stopPropagation();

        var text = '';
        var sel = window.getSelection ? window.getSelection() : null;
        if (sel && !sel.isCollapsed && norm(sel.toString())) {
            text = sel.toString();
        } else {
            var span = e.target.closest('.vml-t[data-vml-o]');
            if (span) text = span.getAttribute('data-vml-o');
            if (!text) text = textAtPoint(e.clientX, e.clientY);
            if (!text) {
                var tc = norm(e.target.innerText || e.target.textContent);
                if (tc && tc.length < 400) text = tc;
            }
            if (!text) {
                var ph = e.target.getAttribute && (e.target.getAttribute('placeholder') || e.target.getAttribute('value'));
                if (ph) text = ph;
            }
        }
        if (!norm(text)) { bar.querySelector('span').textContent = t.empty; return; }
        open(text);
    }, true);
})();
