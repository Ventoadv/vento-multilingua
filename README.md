# Vento Multilingua

Plugin WordPress per rendere un sito multilingua con **URL dedicati per ogni lingua**, **slug tradotti**, traduzione automatica gratuita e correzioni manuali che restano **indicizzabili da Google**.

Esempio con l'italiano come lingua base:

| Lingua   | Indirizzo                         |
| -------- | --------------------------------- |
| Italiano | `https://sito.it/chi-siamo/`      |
| Inglese  | `https://sito.it/en/about-us/`    |
| Tedesco  | `https://sito.it/de/uber-uns/`    |
| Francese | `https://sito.it/fr/qui-sommes-nous/` |

- Versione: 1.4.0
- Requisiti: WordPress 6.0+, PHP 7.4+
- Nessuna chiave API, nessun servizio a pagamento.

---

## Indice

1. [Come funziona](#come-funziona)
2. [Installazione](#installazione)
3. [Impostazioni](#impostazioni)
4. [Slug tradotti](#slug-tradotti)
5. [Titolo SEO e meta description](#titolo-seo-e-meta-description)
6. [Correggere le traduzioni (editor visuale)](#correggere-le-traduzioni-editor-visuale)
7. [Selettore lingue](#selettore-lingue)
8. [SEO: hreflang, canonical, sitemap, Yoast](#seo-hreflang-canonical-sitemap-yoast)
9. [Importa / Esporta CSV](#importa--esporta-csv)
10. [Caricamento senza "flash" di testo italiano](#caricamento-senza-flash-di-testo-italiano)
11. [Per sviluppatori](#per-sviluppatori)
12. [Sicurezza](#sicurezza)
13. [Limiti noti](#limiti-noti)
14. [Disinstallazione](#disinstallazione)

---

## Come funziona

Il plugin usa un approccio **ibrido**: ciò che conta per la SEO viene generato dal server, il resto del testo viene tradotto nel browser.

**Lato server (nell'HTML, quindi visibile a Google):**

- prefisso lingua negli URL (`/en/`, `/de/`, `/fr/`…); la lingua base non ha prefisso;
- slug tradotti per pagine, articoli, tipi di contenuto personalizzati e categorie;
- riscrittura di tutti i link interni della pagina verso la lingua corrente e gli slug tradotti;
- titolo SEO e meta description tradotti;
- correzioni manuali dei testi (dizionario "testo originale → traduzione");
- tag `hreflang`, `canonical`, `<html lang>` e sitemap per lingua.

**Lato browser:**

- il resto del testo viene tradotto automaticamente da **Google Translate** (gratuito);
- le parti già corrette a mano sono marcate `notranslate`, quindi Google non le ritraduce.

### Percorso di una richiesta

Quando un visitatore apre `/en/about-us/`:

1. il plugin riconosce il prefisso `/en/` e imposta la lingua corrente;
2. converte lo slug tradotto `about-us` nel percorso originale `/chi-siamo/`;
3. WordPress genera normalmente la pagina (Elementor e tema inclusi);
4. prima dell'invio, il plugin applica le correzioni manuali e riscrive i link (`/servizi/` → `/en/services/`);
5. nel browser, Google traduce il testo rimanente.

Regole sugli indirizzi:

- se uno slug non è tradotto si usa quello originale (`/de/chi-siamo/`);
- `/en/chi-siamo/` viene reindirizzato con **301** a `/en/about-us/` quando lo slug tradotto esiste;
- `/it/...` (prefisso della lingua base) viene reindirizzato con 301 all'indirizzo senza prefisso;
- `/en/about-us` senza slash finale viene reindirizzato a `/en/about-us/`;
- le pagine figlie seguono il genitore tradotto (`/en/services/cardiology/`), così come paginazione e archivi.

Non vengono aggiunte regole al file `.htaccess`: il prefisso lingua viene gestito dentro WordPress.

---

## Installazione

1. Copia la cartella `vento-multilingua` in `wp-content/plugins/`.
2. Disattiva eventuali altri plugin di traduzione.
3. Attiva **Vento Multilingua** da *Plugin*.
4. Vai su **Multilingua → Impostazioni** e verifica lingua base e lingue aggiuntive.

Alla prima attivazione la lingua base viene presa dalla lingua di WordPress e sono preimpostate inglese, tedesco e francese.

---

## Impostazioni

**Multilingua → Impostazioni**

| Opzione | Descrizione |
| --- | --- |
| Lingua base del sito | La lingua in cui scrivi i contenuti. I suoi URL non hanno prefisso. |
| Lingue aggiuntive | Le lingue disponibili; ognuna ha il suo prefisso (`/en/`, `/de/`…). |
| Traduzione automatica | Attiva Google Translate nel browser per le lingue aggiuntive. |
| Caricamento | Nasconde la pagina con un indicatore finché la traduzione è pronta, con un'attesa massima configurabile (predefinito 4 secondi). |
| Selettore lingue | Stile predefinito, bandiere sì/no, selettore fluttuante e sua posizione, inserimento automatico nell'header (menu o selettore CSS) con stile dedicato. |

---

## Slug tradotti

Puoi impostarli in tre modi:

1. **Nella modifica della pagina/articolo**: box *Traduzioni: slug e SEO*, una riga per lingua.
2. **In blocco**: *Multilingua → Slug tradotti*, una tabella per tipo di contenuto (Pagine, Articoli, Categorie…) con una colonna per lingua.
3. **Da CSV**: *Multilingua → Importa / Esporta* (vedi sotto).

Le categorie e le altre tassonomie hanno i campi nella loro pagina di modifica.

Note:

- lasciare il campo vuoto significa usare lo slug originale;
- lo slug viene "ripulito" come quelli di WordPress (minuscole, trattini, niente accenti);
- se lo slug è già usato allo stesso livello, sia come slug tradotto sia come slug originale di un altro contenuto, viene aggiunto un suffisso (`-2`) per evitare indirizzi ambigui.

---

## Titolo SEO e meta description

Nel box *Traduzioni: slug e SEO* di pagine e articoli (e nella modifica delle categorie) puoi scrivere per ogni lingua:

- **Titolo SEO**: sostituisce il `<title>` della pagina;
- **Meta description**: sostituisce la descrizione.

Con Yoast SEO o Rank Math attivi, questi valori sostituiscono quelli del plugin SEO nelle lingue aggiuntive. Senza plugin SEO, la meta description viene stampata da Vento Multilingua.

---

## Correggere le traduzioni (editor visuale)

Quando la traduzione di Google non va bene:

1. sul sito, da utente amministratore, apri il menu **Traduzioni** nella barra admin;
2. scegli **Modalità traduzione: English** (o un'altra lingua);
3. la pagina si apre in italiano, senza traduzione automatica, con una barra in basso;
4. **clicca** un testo, oppure **selezionane** una parte;
5. scrivi la traduzione e premi **Salva**: la pagina si ricarica con la correzione applicata.

Le correzioni già presenti appaiono sottolineate in verde; cliccandole puoi modificarle o eliminarle.

Come funzionano:

- vengono salvate come coppie *testo originale → traduzione*, una lista per lingua;
- il server sostituisce il testo originale nell'HTML ovunque compaia (parole intere, spazi multipli ignorati), quindi la correzione vale su tutte le pagine;
- non vengono toccati script, stili, campi di testo e SVG;
- la lista completa è in **Multilingua → Traduzioni testi**, con ricerca, modifica, eliminazione e aggiunta manuale.

Ogni testo può essere lungo al massimo 2.000 caratteri.

---

## Selettore lingue

- **Fluttuante**: attivabile nelle impostazioni, in uno dei quattro angoli dello schermo.
- **Nell'header, automatico** (*Impostazioni → Selettore lingue → Inserimento nell'header*), con uno stile dedicato:
  - **come ultima voce del menu**: in tutti i menu (menu classici, widget menu di Elementor e blocco *Navigazione* dei temi a blocchi) oppure in un menu specifico;
  - **dentro un elemento dell'header** indicato con un selettore CSS (es. `header`, `.elementor-location-header .elementor-container`), all'inizio o alla fine. Se l'elemento non esiste nella pagina il selettore non viene mostrato.
- **Shortcode** `[vml_switcher]`, da inserire per esempio nell'header con il widget *Shortcode* di Elementor o con il blocco *Shortcode*.

Opzioni dello shortcode:

| Attributo | Valori | Esempio |
| --- | --- | --- |
| `style` | `dropdown` (menu a tendina), `flags` (solo bandiere), `list` (nomi), `codes` (IT · EN) | `[vml_switcher style="flags"]` |
| `flags` | `1` / `0` | `[vml_switcher style="list" flags="0"]` |

Ogni voce porta alla **stessa pagina** nell'altra lingua, con lo slug tradotto.

---

## SEO: hreflang, canonical, sitemap, Yoast

- **hreflang**: in ogni pagina un tag per ogni lingua più `x-default` (lingua base).
- **canonical**: punta all'URL della lingua corrente, non a quello italiano.
- **`<html lang>`**: impostato sulla lingua corrente.
- **Sitemap dinamiche**: generate a ogni richiesta, senza file statici, con il dominio del sito dove il plugin è installato.
  - Senza plugin SEO: aggiunte a `/wp-sitemap.xml`, per esempio `/wp-sitemap-vmlen-p-page-1.xml`.
  - Con **Yoast SEO**: aggiunte a `/sitemap_index.xml`, per esempio `/vml-en-p-page-sitemap.xml`. Rispettano le esclusioni di Yoast (pagine in noindex, tipi e tassonomie esclusi).
- **Yoast**: canonical, `og:url`, `og:locale` (es. `en_GB`) e dati schema puntano alla lingua corrente.

---

## Importa / Esporta CSV

**Multilingua → Importa / Esporta**

**Traduzioni testi:**

```csv
original,en,de,fr
Chi siamo,About us,Über uns,Qui sommes-nous
Prenota una visita,Book a visit,Termin buchen,Prendre rendez-vous
```

**Slug tradotti:**

```csv
type,id,title,slug,en,de,fr
page,12,Chi siamo,chi-siamo,about-us,uber-uns,qui-sommes-nous
tax:category,3,Notizie,notizie,news,nachrichten,actualites
```

- I file sono UTF-8 e si aprono con Excel o Google Sheets. In importazione è accettato anche il punto e virgola come separatore.
- Le celle vuote vengono ignorate. Per i testi puoi scegliere se unire le traduzioni a quelle esistenti o sostituirle tutte.
- Le celle che inizierebbero con `=`, `+`, `-` o `@` vengono protette con un apostrofo in esportazione (anti "CSV injection") e ripristinate in importazione.

---

## Caricamento senza "flash" di testo italiano

Senza accorgimenti, nelle lingue aggiuntive si vedrebbe per un attimo il testo italiano prima della traduzione. Il plugin:

1. nasconde il contenuto e mostra un piccolo indicatore di caricamento;
2. attende che Google abbia finito di tradurre la pagina;
3. mostra la pagina con una breve dissolvenza.

Se Google è lento o non raggiungibile, la pagina viene mostrata comunque dopo l'attesa massima. La funzione si disattiva in *Impostazioni → Traduzione automatica*. Non è attiva sulla lingua base né in modalità traduzione.

---

## Per sviluppatori

### Funzioni

| Funzione | Restituisce |
| --- | --- |
| `vml_current_lang()` | Codice della lingua corrente (`it`, `en`…) |
| `vml_url( 'en' )` | URL della pagina corrente nella lingua indicata |
| `vml_switcher( [ 'style' => 'flags' ] )` | HTML del selettore lingue |

### Filtri

| Filtro | Uso |
| --- | --- |
| `vml_languages` | Aggiungere o modificare le lingue disponibili |
| `vml_post_types` | Tipi di contenuto con slug tradotti |
| `vml_taxonomies` | Tassonomie con slug tradotti |
| `vml_capability` | Permesso richiesto per gestire le traduzioni (predefinito `manage_options`) |
| `vml_is_excluded` | Escludere richieste dalla gestione multilingua |
| `vml_sitemaps_enabled` | Disattivare le sitemap per lingua |
| `vml_sitemap_entry` | Modificare una voce della sitemap |
| `vml_og_locales` | Mappa lingua → `og:locale` |

### Dati salvati

| Dato | Dove |
| --- | --- |
| Impostazioni | opzione `vml_settings` |
| Correzioni testi | opzioni `vml_strings_{lingua}` |
| Slug, titolo e descrizione tradotti | meta `_vml_slug_{lingua}`, `_vml_title_{lingua}`, `_vml_desc_{lingua}` su post e termini |
| Mappa degli slug | transient `vml_slug_maps`, rigenerato a ogni modifica |

### Struttura dei file

```
vento-multilingua/
├── vento-multilingua.php            avvio, API pubblica, sitemap WP
├── includes/
│   ├── class-vml-languages.php      elenco lingue, bandiere SVG
│   ├── class-vml-settings.php       impostazioni
│   ├── class-vml-router.php         prefisso lingua, redirect, URL localizzati
│   ├── class-vml-slugs.php          slug tradotti e mappe dei percorsi
│   ├── class-vml-strings.php        dizionario delle correzioni
│   ├── class-vml-frontend.php       HTML lato server, SEO, selettore, Google
│   ├── class-vml-editor.php         editor visuale e AJAX
│   ├── class-vml-meta.php           box slug/SEO su post e termini
│   ├── class-vml-admin.php          pagine di amministrazione, CSV
│   ├── class-vml-sitemaps.php       dati sitemap e integrazione Yoast
│   └── class-vml-sitemap-provider.php  provider per le sitemap native WP
├── assets/                          CSS e JS (frontend, editor, admin)
├── readme.txt
└── uninstall.php
```

Il plugin non modifica la lingua di WordPress (locale): le stringhe di tema e plugin restano nella lingua base e vengono tradotte da Google nel browser.

---

## Sicurezza

- Tutte le azioni di salvataggio richiedono il permesso adeguato (`manage_options` o `edit_post`) e un nonce WordPress (protezione CSRF).
- Gli input vengono sanificati e ogni valore stampato viene escapato: una traduzione contenente HTML o script viene mostrata come testo.
- Le query al database usano `$wpdb->prepare`.
- I redirect usano `wp_safe_redirect` e restano sul dominio del sito.
- L'upload CSV legge solo il file temporaneo caricato, con un limite di 10 MB.
- I messaggi dell'area admin non sono manipolabili dall'URL.

---

## Limiti noti

- Il testo tradotto da Google esiste solo nel browser. Google lo indicizza solo se esegue il JavaScript della pagina. Le parti generate dal server (slug, titoli, meta, correzioni manuali) sono sempre indicizzate. Più correzioni manuali inserisci, più la pagina tradotta è solida per la SEO.
- Le correzioni sostituiscono testi esatti: se il testo originale cambia, la correzione va aggiornata.
- Con Rank Math sono gestiti titolo, descrizione e canonical, ma le sitemap per lingua sono integrate solo con le sitemap native di WordPress e con Yoast.

---

## Disinstallazione

Eliminando il plugin da *Plugin → Elimina* vengono rimossi impostazioni, correzioni e meta degli slug tradotti (`uninstall.php`). La semplice disattivazione non cancella nessun dato.
