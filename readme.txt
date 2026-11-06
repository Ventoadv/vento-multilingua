=== Vento Multilingua ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.3.2
License: GPLv2 or later

Sito multilingua con URL per lingua, slug tradotti, traduzione automatica Google gratuita e correzioni manuali indicizzabili.

== Come funziona ==

* Lingua base (es. italiano) senza prefisso: /chi-siamo/
* Lingue aggiuntive con prefisso e slug tradotto: /en/about-us/, /de/uber-uns/, /fr/qui-sommes-nous/
* Se lo slug tradotto non è impostato si usa quello originale (/de/chi-siamo/).
* /en/chi-siamo/ viene reindirizzato (301) a /en/about-us/ quando esiste lo slug tradotto.
* Tutti i link interni della pagina vengono riscritti nella lingua corrente.
* Il testo viene tradotto da Google Translate nel browser (gratuito, nessuna chiave API).
* Titolo SEO, meta description, slug e correzioni manuali sono inseriti lato server nell'HTML (indicizzabili) e Google non li ritraduce.
* Tag hreflang per tutte le lingue + x-default, canonical e <html lang> corretti.
* Sitemap dinamiche per lingua dentro /wp-sitemap.xml (es. /wp-sitemap-vmlen-p-page-1.xml), generate a ogni richiesta con il dominio corrente. Con Yoast SEO attivo le sitemap per lingua vengono aggiunte a /sitemap_index.xml (es. /vml-en-p-page-sitemap.xml) e canonical, og:url, og:locale e schema puntano alla lingua corrente.

== Installazione ==

1. Disattiva eventuali altri plugin di traduzione.
2. Attiva Vento Multilingua.
3. Multilingua → Impostazioni: verifica lingua base e lingue aggiuntive.
4. Impostazioni → Permalink: premi "Salva" una volta (non obbligatorio, ma consigliato).

== Uso ==

* Slug e SEO: box "Traduzioni: slug e SEO" nella modifica di pagine/articoli, oppure Multilingua → Slug tradotti per modificarli tutti in tabella. Le categorie hanno i campi nella loro pagina di modifica.
* Correzioni testi: dalla barra admin sul sito → "Traduzioni" → "Modalità traduzione: English"; clicca un testo, scrivi la traduzione, salva. Elenco completo in Multilingua → Traduzioni testi.
* Import/Export CSV di testi e slug in Multilingua → Importa / Esporta.
* Selettore lingue: fluttuante (impostazioni) oppure shortcode [vml_switcher] — style="dropdown|flags|list|codes", flags="0|1".

== Funzioni per sviluppatori ==

* vml_current_lang() — codice lingua corrente
* vml_url( 'en' ) — URL della pagina corrente in una lingua
* vml_switcher( [ 'style' => 'flags' ] ) — HTML del selettore
* Filtri: vml_languages, vml_post_types, vml_taxonomies, vml_capability, vml_is_excluded
