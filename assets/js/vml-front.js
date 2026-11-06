/* Vento Multilingua - frontend: traduzione automatica Google nel browser + selettore lingue */
(function () {
    var c = window.vmlFront || {};

    function setGoogCookie(value) {
        var host = location.hostname;
        var base = host.replace(/^www\./, '');
        var expired = '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        var suffix = value ? '' : expired;
        var v = 'googtrans=' + (value || '') + suffix + '; path=/';
        document.cookie = v;
        if (host.indexOf('.') !== -1) {
            document.cookie = v + '; domain=' + host;
            document.cookie = v + '; domain=.' + base;
        }
    }

    var reveal = function () { if (window.vmlReveal) window.vmlReveal(); };

    // Mostra la pagina quando Google ha finito: la classe translated-* compare all'avvio della
    // traduzione, poi si attende che il DOM resti fermo per un attimo (la traduzione arriva a blocchi).
    function watchTranslation() {
        if (!window.MutationObserver || !window.vmlReveal) return;
        var html = document.documentElement, quietTimer = null, contentObs = null;
        function done() {
            if (contentObs) contentObs.disconnect();
            reveal();
        }
        function bump() {
            clearTimeout(quietTimer);
            quietTimer = setTimeout(done, 300);
        }
        function started() {
            return /\btranslated-(ltr|rtl)\b/.test(html.className);
        }
        function start() {
            contentObs = new MutationObserver(bump);
            contentObs.observe(document.body, { childList: true, subtree: true, characterData: true });
            bump();
        }
        if (started()) { start(); return; }
        var classObs = new MutationObserver(function () {
            if (started()) { classObs.disconnect(); start(); }
        });
        classObs.observe(html, { attributes: true, attributeFilter: ['class'] });
    }

    if (!c.google) {
        // Lingua base o traduzione disattivata: nessuna traduzione residua.
        setGoogCookie('');
        reveal();
    } else {
        watchTranslation();
        setGoogCookie('/' + c.source + '/' + c.target);
        window.vmlGoogleInit = function () {
            /* global google */
            new google.translate.TranslateElement({
                pageLanguage: c.source,
                includedLanguages: c.target,
                autoDisplay: false
            }, 'vml-google-element');
        };
        var s = document.createElement('script');
        s.src = 'https://translate.google.com/translate_a/element.js?cb=vmlGoogleInit';
        s.async = true;
        s.onerror = reveal; // Google non raggiungibile: mostra subito la pagina
        document.head.appendChild(s);

        // Google aggiunge "top" al body e un tooltip: neutralizza.
        var fixTop = function () { if (document.body && document.body.style.top) document.body.style.top = ''; };
        if (window.MutationObserver) {
            new MutationObserver(fixTop).observe(document.body, { attributes: true, attributeFilter: ['style'] });
        }
    }

    // Selettore a tendina: apertura con click/touch e chiusura fuori.
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest ? e.target.closest('.vml-toggle') : null;
        var open = document.querySelectorAll('.vml-switcher.vml-open');
        for (var i = 0; i < open.length; i++) {
            if (!toggle || open[i] !== toggle.parentNode) open[i].classList.remove('vml-open');
        }
        if (toggle) {
            e.preventDefault();
            toggle.parentNode.classList.toggle('vml-open');
            toggle.setAttribute('aria-expanded', toggle.parentNode.classList.contains('vml-open') ? 'true' : 'false');
        }
    });
})();
