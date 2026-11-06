<?php
if (!defined('ABSPATH')) exit;

class VML_Admin
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_notices', [__CLASS__, 'notices']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_post_vml_save_settings', [__CLASS__, 'save_settings']);
        add_action('admin_post_vml_save_strings', [__CLASS__, 'save_strings']);
        add_action('admin_post_vml_save_slugs', [__CLASS__, 'save_slugs']);
        add_action('admin_post_vml_export_strings', [__CLASS__, 'export_strings']);
        add_action('admin_post_vml_import_strings', [__CLASS__, 'import_strings']);
        add_action('admin_post_vml_export_slugs', [__CLASS__, 'export_slugs']);
        add_action('admin_post_vml_import_slugs', [__CLASS__, 'import_slugs']);
        add_filter('plugin_action_links_' . plugin_basename(VML_FILE), function ($links) {
            array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=vml-settings')) . '">' . esc_html__('Impostazioni', 'vento-multilingua') . '</a>');
            return $links;
        });
    }

    public static function menu()
    {
        $cap = VML_Settings::capability();
        add_menu_page('Vento Multilingua', 'Multilingua', $cap, 'vml-settings', [__CLASS__, 'page_settings'], 'dashicons-translation', 81);
        add_submenu_page('vml-settings', __('Impostazioni', 'vento-multilingua'), __('Impostazioni', 'vento-multilingua'), $cap, 'vml-settings', [__CLASS__, 'page_settings']);
        add_submenu_page('vml-settings', __('Traduzioni testi', 'vento-multilingua'), __('Traduzioni testi', 'vento-multilingua'), $cap, 'vml-strings', [__CLASS__, 'page_strings']);
        add_submenu_page('vml-settings', __('Slug tradotti', 'vento-multilingua'), __('Slug tradotti', 'vento-multilingua'), $cap, 'vml-slugs', [__CLASS__, 'page_slugs']);
        add_submenu_page('vml-settings', __('Importa / Esporta', 'vento-multilingua'), __('Importa / Esporta', 'vento-multilingua'), $cap, 'vml-tools', [__CLASS__, 'page_tools']);
    }

    public static function assets($hook)
    {
        if (strpos((string) $hook, 'vml-') === false) return;
        wp_enqueue_style('vml-admin', VML_URL . 'assets/css/vml-admin.css', [], VML_VERSION);
    }

    public static function notices()
    {
        if (!current_user_can('activate_plugins')) return;
        $key = 'vml_msg_' . get_current_user_id();
        $msg = get_transient($key);
        if ($msg) {
            delete_transient($key);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }
    }

    private static function guard($action)
    {
        if (!current_user_can(VML_Settings::capability())) wp_die(esc_html__('Non autorizzato', 'vento-multilingua'));
        check_admin_referer($action);
    }

    private static function back($page, $msg, $extra = [])
    {
        set_transient('vml_msg_' . get_current_user_id(), (string) $msg, 60);
        wp_safe_redirect(add_query_arg(array_merge(['page' => $page], $extra), admin_url('admin.php')));
        exit;
    }

    private static function lang_tabs($page, $current, $extra = [])
    {
        echo '<h2 class="nav-tab-wrapper">';
        foreach (VML_Settings::extra_languages() as $l) {
            $url = add_query_arg(array_merge(['page' => $page, 'lang' => $l], $extra), admin_url('admin.php'));
            echo '<a class="nav-tab' . ($l === $current ? ' nav-tab-active' : '') . '" href="' . esc_url($url) . '">' . VML_Languages::flag($l) . ' ' . esc_html(VML_Languages::native_name($l)) . '</a>'; // phpcs:ignore
        }
        echo '</h2>';
    }

    private static function current_lang_param()
    {
        $extra = VML_Settings::extra_languages();
        $l = isset($_GET['lang']) ? sanitize_key(wp_unslash($_GET['lang'])) : ''; // phpcs:ignore
        return in_array($l, $extra, true) ? $l : ($extra[0] ?? '');
    }

    /* ================= Impostazioni ================= */

    public static function page_settings()
    {
        $s = VML_Settings::get();
        $all = VML_Languages::all();
        ?>
        <div class="wrap vml-wrap">
            <h1>Vento Multilingua</h1>
            <p class="vml-lead"><?php esc_html_e('Ogni lingua ha i suoi URL (es. /en/chi-siamo-tradotto/). Il testo viene tradotto automaticamente da Google nel browser; slug, titoli SEO, meta description e le tue correzioni sono inseriti direttamente nell\'HTML e quindi indicizzabili.', 'vento-multilingua'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('vml_save_settings'); ?>
                <input type="hidden" name="action" value="vml_save_settings">
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><?php esc_html_e('Lingua base del sito', 'vento-multilingua'); ?></th><td>
                        <select name="default_lang"><?php foreach ($all as $code => $l) printf('<option value="%s"%s>%s (%s)</option>', esc_attr($code), selected($s['default_lang'], $code, false), esc_html($l[1]), esc_html($code)); ?></select>
                        <p class="description"><?php esc_html_e('La lingua in cui scrivi i contenuti. I suoi URL non hanno prefisso.', 'vento-multilingua'); ?></p>
                    </td></tr>
                    <tr><th scope="row"><?php esc_html_e('Lingue aggiuntive', 'vento-multilingua'); ?></th><td>
                        <div class="vml-lang-grid">
                        <?php foreach ($all as $code => $l) : ?>
                            <label><input type="checkbox" name="languages[]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, (array) $s['languages'], true)); ?>> <?php echo VML_Languages::flag($code); // phpcs:ignore ?> <?php echo esc_html($l[1]); ?> <code>/<?php echo esc_html($code); ?>/</code></label>
                        <?php endforeach; ?>
                        </div>
                    </td></tr>
                    <tr><th scope="row"><?php esc_html_e('Traduzione automatica', 'vento-multilingua'); ?></th><td>
                        <label><input type="checkbox" name="auto_translate" value="1" <?php checked($s['auto_translate']); ?>> <?php esc_html_e('Traduci il testo con Google Translate nel browser del visitatore (gratuito)', 'vento-multilingua'); ?></label>
                        <p><label><input type="checkbox" name="preload_hide" value="1" <?php checked($s['preload_hide']); ?>> <?php esc_html_e('Mostra un caricamento e nascondi la pagina finché la traduzione non è pronta (evita di vedere prima il testo italiano)', 'vento-multilingua'); ?></label></p>
                        <p><label><?php esc_html_e('Attesa massima', 'vento-multilingua'); ?> <input type="number" name="preload_max" min="1" max="10" step="0.5" value="<?php echo esc_attr($s['preload_max']); ?>" style="width:70px"> <?php esc_html_e('secondi (poi la pagina viene mostrata comunque)', 'vento-multilingua'); ?></label></p>
                    </td></tr>
                    <tr><th scope="row"><?php esc_html_e('Selettore lingue', 'vento-multilingua'); ?></th><td>
                        <p><label><?php esc_html_e('Stile predefinito', 'vento-multilingua'); ?>
                            <select name="switcher_style">
                                <?php foreach (['dropdown' => 'Menu a tendina', 'flags' => 'Solo bandiere', 'list' => 'Elenco nomi', 'codes' => 'Codici (IT · EN)'] as $k => $v) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($s['switcher_style'], $k, false), esc_html($v)); ?>
                            </select></label></p>
                        <p><label><input type="checkbox" name="show_flags" value="1" <?php checked($s['show_flags']); ?>> <?php esc_html_e('Mostra bandiere', 'vento-multilingua'); ?></label></p>
                        <p><label><input type="checkbox" name="float_switcher" value="1" <?php checked($s['float_switcher']); ?>> <?php esc_html_e('Selettore fluttuante', 'vento-multilingua'); ?></label>
                            <select name="float_position">
                                <?php foreach (['bottom-right' => 'In basso a destra', 'bottom-left' => 'In basso a sinistra', 'top-right' => 'In alto a destra', 'top-left' => 'In alto a sinistra'] as $k => $v) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($s['float_position'], $k, false), esc_html($v)); ?>
                            </select></p>
                        <p class="description"><?php echo wp_kses_post(__('Per inserirlo nell\'header usa lo shortcode <code>[vml_switcher]</code> (widget Shortcode di Elementor o blocco Shortcode). Opzioni: <code>[vml_switcher style="flags"]</code>, <code>style="list"</code>, <code>style="codes"</code>, <code>flags="0"</code>.', 'vento-multilingua')); ?></p>
                    </td></tr>
                </table>
                <?php submit_button(__('Salva impostazioni', 'vento-multilingua')); ?>
            </form>
        </div>
        <?php
    }

    public static function save_settings()
    {
        self::guard('vml_save_settings');
        $all = VML_Languages::all();
        $default = isset($_POST['default_lang']) ? sanitize_key(wp_unslash($_POST['default_lang'])) : 'it';
        if (!isset($all[$default])) $default = 'it';
        $langs = isset($_POST['languages']) && is_array($_POST['languages']) ? array_map('sanitize_key', wp_unslash($_POST['languages'])) : [];
        $langs = array_values(array_filter($langs, function ($l) use ($all, $default) {
            return isset($all[$l]) && $l !== $default;
        }));
        $style = isset($_POST['switcher_style']) ? sanitize_key(wp_unslash($_POST['switcher_style'])) : 'dropdown';
        $pos = isset($_POST['float_position']) ? sanitize_key(wp_unslash($_POST['float_position'])) : 'bottom-right';
        $current = VML_Settings::get();
        VML_Settings::save(array_merge($current, [
            'default_lang'   => $default,
            'languages'      => $langs,
            'auto_translate' => empty($_POST['auto_translate']) ? 0 : 1,
            'preload_hide'   => empty($_POST['preload_hide']) ? 0 : 1,
            'preload_max'    => isset($_POST['preload_max']) ? min(10, max(1, (float) $_POST['preload_max'])) : 4,
            'float_switcher' => empty($_POST['float_switcher']) ? 0 : 1,
            'float_position' => in_array($pos, ['bottom-right', 'bottom-left', 'top-right', 'top-left'], true) ? $pos : 'bottom-right',
            'switcher_style' => in_array($style, ['dropdown', 'flags', 'list', 'codes'], true) ? $style : 'dropdown',
            'show_flags'     => empty($_POST['show_flags']) ? 0 : 1,
        ]));
        self::back('vml-settings', __('Impostazioni salvate.', 'vento-multilingua'));
    }

    /* ================= Stringhe ================= */

    public static function page_strings()
    {
        $lang = self::current_lang_param();
        echo '<div class="wrap vml-wrap"><h1>' . esc_html__('Traduzioni testi', 'vento-multilingua') . '</h1>';
        if (!$lang) {
            echo '<p>' . esc_html__('Attiva almeno una lingua aggiuntiva nelle impostazioni.', 'vento-multilingua') . '</p></div>';
            return;
        }
        echo '<p class="vml-lead">' . esc_html__('Correzioni manuali: il testo originale viene sostituito con la tua traduzione direttamente nell\'HTML e Google non lo ritraduce. Puoi aggiungerle anche dal sito con "Modalità traduzione" nella barra admin.', 'vento-multilingua') . '</p>';
        self::lang_tabs('vml-strings', $lang);

        $strings = VML_Strings::get($lang);
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : ''; // phpcs:ignore
        if ($search !== '') {
            $strings = array_filter($strings, function ($t, $o) use ($search) {
                return mb_stripos($o, $search) !== false || mb_stripos($t, $search) !== false;
            }, ARRAY_FILTER_USE_BOTH);
        }
        $per = 100;
        $total = count($strings);
        $paged = max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1); // phpcs:ignore
        $rows = array_slice($strings, ($paged - 1) * $per, $per, true);
        ?>
        <form method="get" class="vml-search">
            <input type="hidden" name="page" value="vml-strings"><input type="hidden" name="lang" value="<?php echo esc_attr($lang); ?>">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Cerca…', 'vento-multilingua'); ?>">
            <button class="button"><?php esc_html_e('Cerca', 'vento-multilingua'); ?></button>
            <span class="vml-count"><?php echo esc_html(sprintf(_n('%d traduzione', '%d traduzioni', $total, 'vento-multilingua'), $total)); ?></span>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('vml_save_strings'); ?>
            <input type="hidden" name="action" value="vml_save_strings">
            <input type="hidden" name="lang" value="<?php echo esc_attr($lang); ?>">
            <table class="widefat striped vml-table">
                <thead><tr><th><?php esc_html_e('Testo originale', 'vento-multilingua'); ?></th><th><?php echo esc_html(VML_Languages::native_name($lang)); ?></th><th class="vml-del"><?php esc_html_e('Elimina', 'vento-multilingua'); ?></th></tr></thead>
                <tbody>
                <?php $i = 0; foreach ($rows as $o => $t) : ?>
                    <tr>
                        <td><input type="hidden" name="orig[<?php echo (int) $i; ?>]" value="<?php echo esc_attr($o); ?>"><?php echo esc_html($o); ?></td>
                        <td><textarea name="trans[<?php echo (int) $i; ?>]" rows="1"><?php echo esc_textarea($t); ?></textarea></td>
                        <td class="vml-del"><input type="checkbox" name="del[<?php echo (int) $i; ?>]" value="1"></td>
                    </tr>
                <?php $i++; endforeach; ?>
                    <tr class="vml-new">
                        <td><textarea name="new_orig" rows="1" placeholder="<?php esc_attr_e('Nuovo testo originale…', 'vento-multilingua'); ?>"></textarea></td>
                        <td><textarea name="new_trans" rows="1" placeholder="<?php esc_attr_e('Traduzione…', 'vento-multilingua'); ?>"></textarea></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            <?php
            $pages = (int) ceil($total / $per);
            if ($pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links(['base' => add_query_arg('paged', '%#%', remove_query_arg(['_wpnonce', '_wp_http_referer'])), 'format' => '', 'total' => $pages, 'current' => $paged]) . '</div></div>'; // phpcs:ignore
            }
            submit_button(__('Salva traduzioni', 'vento-multilingua'));
            ?>
        </form></div>
        <?php
    }

    public static function save_strings()
    {
        self::guard('vml_save_strings');
        $lang = isset($_POST['lang']) ? sanitize_key(wp_unslash($_POST['lang'])) : '';
        if (!VML_Settings::is_extra($lang)) wp_die('Lingua non valida');
        $all = VML_Strings::get($lang);
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput
        $orig = isset($_POST['orig']) && is_array($_POST['orig']) ? wp_unslash($_POST['orig']) : [];
        $trans = isset($_POST['trans']) && is_array($_POST['trans']) ? wp_unslash($_POST['trans']) : [];
        $del = isset($_POST['del']) && is_array($_POST['del']) ? $_POST['del'] : [];
        // phpcs:enable
        foreach ($orig as $i => $o) {
            $o = VML_Strings::normalize(sanitize_text_field($o));
            if (!empty($del[$i]) || !isset($trans[$i]) || trim($trans[$i]) === '') {
                unset($all[$o]);
                continue;
            }
            $all[$o] = sanitize_text_field($trans[$i]);
        }
        $all = array_filter($all, function ($t, $o) {
            return mb_strlen($o) <= VML_Strings::MAX_LEN && mb_strlen($t) <= VML_Strings::MAX_LEN;
        }, ARRAY_FILTER_USE_BOTH);
        $no = isset($_POST['new_orig']) ? sanitize_text_field(wp_unslash($_POST['new_orig'])) : '';
        $nt = isset($_POST['new_trans']) ? sanitize_text_field(wp_unslash($_POST['new_trans'])) : '';
        if ($no !== '' && $nt !== '') $all[$no] = $nt;
        VML_Strings::save_all($lang, $all);
        self::back('vml-strings', __('Traduzioni salvate.', 'vento-multilingua'), ['lang' => $lang]);
    }

    /* ================= Slug ================= */

    private static function slug_tabs()
    {
        $tabs = [];
        foreach (VML_Meta::post_types() as $pt) {
            $o = get_post_type_object($pt);
            if ($o) $tabs['pt:' . $pt] = $o->labels->name;
        }
        foreach (VML_Meta::taxonomies() as $tx) {
            $o = get_taxonomy($tx);
            if ($o) $tabs['tx:' . $tx] = $o->labels->name;
        }
        return $tabs;
    }

    public static function page_slugs()
    {
        $langs = VML_Settings::extra_languages();
        echo '<div class="wrap vml-wrap"><h1>' . esc_html__('Slug tradotti', 'vento-multilingua') . '</h1>';
        if (!$langs) {
            echo '<p>' . esc_html__('Attiva almeno una lingua aggiuntiva nelle impostazioni.', 'vento-multilingua') . '</p></div>';
            return;
        }
        echo '<p class="vml-lead">' . esc_html__('Scrivi lo slug per ogni lingua (es. "chi-siamo" → "about-us" diventa /en/about-us/). Se lo lasci vuoto si usa lo slug originale. Il vecchio indirizzo con lo slug italiano viene reindirizzato (301) a quello tradotto.', 'vento-multilingua') . '</p>';

        $tabs = self::slug_tabs();
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'pt:page'; // phpcs:ignore
        if (!isset($tabs[$tab])) $tab = (string) key($tabs);
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $k => $label) {
            echo '<a class="nav-tab' . ($k === $tab ? ' nav-tab-active' : '') . '" href="' . esc_url(add_query_arg(['page' => 'vml-slugs', 'tab' => $k], admin_url('admin.php'))) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        list($kind, $name) = explode(':', $tab, 2);
        $per = 50;
        $paged = max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1); // phpcs:ignore
        $items = [];
        $total = 0;
        if ($kind === 'pt') {
            $q = new WP_Query(['post_type' => $name, 'post_status' => ['publish', 'draft', 'private', 'pending', 'future'], 'posts_per_page' => $per, 'paged' => $paged, 'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'], 'no_found_rows' => false]);
            $total = (int) $q->found_posts;
            foreach ($q->posts as $p) {
                $slugs = [];
                foreach ($langs as $l) $slugs[$l] = urldecode((string) get_post_meta($p->ID, '_vml_slug_' . $l, true));
                $depth = count(get_post_ancestors($p));
                $items[] = ['id' => $p->ID, 'title' => str_repeat('— ', $depth) . ($p->post_title ?: '(senza titolo)'), 'slug' => urldecode($p->post_name), 'edit' => get_edit_post_link($p->ID), 'status' => $p->post_status, 'slugs' => $slugs];
            }
        } else {
            $total = (int) wp_count_terms(['taxonomy' => $name, 'hide_empty' => false]);
            $terms = get_terms(['taxonomy' => $name, 'hide_empty' => false, 'number' => $per, 'offset' => ($paged - 1) * $per]);
            if (!is_wp_error($terms)) {
                foreach ($terms as $t) {
                    $slugs = [];
                    foreach ($langs as $l) $slugs[$l] = urldecode((string) get_term_meta($t->term_id, '_vml_slug_' . $l, true));
                    $items[] = ['id' => $t->term_id, 'title' => $t->name, 'slug' => urldecode($t->slug), 'edit' => get_edit_term_link($t->term_id, $name), 'status' => 'publish', 'slugs' => $slugs];
                }
            }
        }
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('vml_save_slugs'); ?>
            <input type="hidden" name="action" value="vml_save_slugs">
            <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
            <input type="hidden" name="paged" value="<?php echo (int) $paged; ?>">
            <table class="widefat striped vml-table">
                <thead><tr><th><?php esc_html_e('Titolo', 'vento-multilingua'); ?></th><th><?php echo esc_html(VML_Languages::native_name(VML_Settings::default_lang())); ?></th>
                <?php foreach ($langs as $l) echo '<th>' . VML_Languages::flag($l) . ' ' . esc_html(VML_Languages::native_name($l)) . '</th>'; // phpcs:ignore ?></tr></thead>
                <tbody>
                <?php if (!$items) echo '<tr><td colspan="' . (2 + count($langs)) . '">' . esc_html__('Nessun elemento.', 'vento-multilingua') . '</td></tr>'; ?>
                <?php foreach ($items as $it) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url($it['edit']); ?>"><?php echo esc_html($it['title']); ?></a><?php if ($it['status'] !== 'publish') echo ' <span class="vml-status">' . esc_html($it['status']) . '</span>'; ?></td>
                        <td><code><?php echo esc_html($it['slug']); ?></code></td>
                        <?php foreach ($langs as $l) : ?>
                            <td><input type="text" name="slug[<?php echo (int) $it['id']; ?>][<?php echo esc_attr($l); ?>]" value="<?php echo esc_attr($it['slugs'][$l]); ?>" placeholder="<?php echo esc_attr($it['slug']); ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $pages = (int) ceil($total / $per);
            if ($pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links(['base' => add_query_arg('paged', '%#%', remove_query_arg(['_wpnonce', '_wp_http_referer'])), 'format' => '', 'total' => $pages, 'current' => $paged]) . '</div></div>'; // phpcs:ignore
            }
            submit_button(__('Salva slug', 'vento-multilingua'));
            ?>
        </form></div>
        <?php
    }

    public static function save_slugs()
    {
        self::guard('vml_save_slugs');
        $tab = isset($_POST['tab']) ? sanitize_text_field(wp_unslash($_POST['tab'])) : '';
        $paged = isset($_POST['paged']) ? (int) $_POST['paged'] : 1;
        if (strpos($tab, ':') === false) wp_die('Tab non valida');
        list($kind, $name) = explode(':', $tab, 2);
        $langs = VML_Settings::extra_languages();
        $data = isset($_POST['slug']) && is_array($_POST['slug']) ? wp_unslash($_POST['slug']) : []; // phpcs:ignore
        foreach ($data as $id => $per_lang) {
            $id = (int) $id;
            if (!is_array($per_lang)) continue;
            foreach ($langs as $l) {
                if (!array_key_exists($l, $per_lang)) continue;
                if ($kind === 'pt') {
                    if (get_post_type($id) !== $name || !current_user_can('edit_post', $id)) continue;
                    VML_Slugs::save_post_slug($id, $l, $per_lang[$l]);
                } else {
                    $term = get_term($id, $name);
                    if (!$term || is_wp_error($term) || !current_user_can('manage_categories')) continue;
                    VML_Slugs::save_term_slug($id, $name, $l, $per_lang[$l]);
                }
            }
        }
        VML_Slugs::flush();
        self::back('vml-slugs', __('Slug salvati.', 'vento-multilingua'), ['tab' => $tab, 'paged' => $paged]);
    }

    /* ================= Import / Export ================= */

    public static function page_tools()
    {
        $langs = VML_Settings::extra_languages();
        $post = esc_url(admin_url('admin-post.php'));
        ?>
        <div class="wrap vml-wrap">
            <h1><?php esc_html_e('Importa / Esporta', 'vento-multilingua'); ?></h1>
            <p class="vml-lead"><?php esc_html_e('File CSV (separatore virgola, UTF-8) da aprire con Excel o Google Sheets. La prima riga contiene le intestazioni.', 'vento-multilingua'); ?></p>
            <div class="vml-cards">
                <div class="vml-card">
                    <h2><?php esc_html_e('Traduzioni testi', 'vento-multilingua'); ?></h2>
                    <p><?php echo esc_html(sprintf(__('Colonne: original, %s', 'vento-multilingua'), implode(', ', $langs))); ?></p>
                    <form method="post" action="<?php echo $post; // phpcs:ignore ?>"><?php wp_nonce_field('vml_export_strings'); ?><input type="hidden" name="action" value="vml_export_strings"><?php submit_button(__('Esporta CSV', 'vento-multilingua'), 'secondary', 'submit', false); ?></form>
                    <hr>
                    <form method="post" enctype="multipart/form-data" action="<?php echo $post; // phpcs:ignore ?>"><?php wp_nonce_field('vml_import_strings'); ?><input type="hidden" name="action" value="vml_import_strings">
                        <input type="file" name="file" accept=".csv,text/csv" required>
                        <p><label><input type="checkbox" name="replace" value="1"> <?php esc_html_e('Sostituisci tutte le traduzioni esistenti (altrimenti unisci)', 'vento-multilingua'); ?></label></p>
                        <?php submit_button(__('Importa CSV', 'vento-multilingua'), 'primary', 'submit', false); ?></form>
                </div>
                <div class="vml-card">
                    <h2><?php esc_html_e('Slug tradotti', 'vento-multilingua'); ?></h2>
                    <p><?php echo esc_html(sprintf(__('Colonne: type, id, title, slug, %s', 'vento-multilingua'), implode(', ', $langs))); ?></p>
                    <form method="post" action="<?php echo $post; // phpcs:ignore ?>"><?php wp_nonce_field('vml_export_slugs'); ?><input type="hidden" name="action" value="vml_export_slugs"><?php submit_button(__('Esporta CSV', 'vento-multilingua'), 'secondary', 'submit', false); ?></form>
                    <hr>
                    <form method="post" enctype="multipart/form-data" action="<?php echo $post; // phpcs:ignore ?>"><?php wp_nonce_field('vml_import_slugs'); ?><input type="hidden" name="action" value="vml_import_slugs">
                        <input type="file" name="file" accept=".csv,text/csv" required>
                        <?php submit_button(__('Importa CSV', 'vento-multilingua'), 'primary', 'submit', false); ?></form>
                </div>
            </div>
        </div>
        <?php
    }

    private static function send_csv($filename, array $rows)
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM per Excel
        foreach ($rows as $r) fputcsv($out, array_map([__CLASS__, 'csv_safe'], $r));
        fclose($out);
        exit;
    }

    /** Evita che Excel interpreti una cella come formula (CSV injection). */
    public static function csv_safe($v)
    {
        $v = (string) $v;
        return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
    }

    /** Inverso di csv_safe in importazione. */
    private static function csv_unsafe($v)
    {
        $v = (string) $v;
        return (strlen($v) > 1 && $v[0] === "'" && strpos("=+-@\t\r", $v[1]) !== false) ? substr($v, 1) : $v;
    }

    private static function read_csv()
    {
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) wp_die(esc_html__('Nessun file caricato.', 'vento-multilingua')); // phpcs:ignore
        if ((int) $_FILES['file']['size'] > 10 * MB_IN_BYTES) wp_die(esc_html__('File troppo grande.', 'vento-multilingua'));
        $content = file_get_contents($_FILES['file']['tmp_name']); // phpcs:ignore
        $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) $content);
        // separatore: virgola o punto e virgola (Excel italiano)
        $first = strtok($content, "\n");
        $sep = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        $h = fopen('php://temp', 'r+');
        fwrite($h, $content);
        rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $sep)) !== false) {
            if ($r === [null]) continue;
            $rows[] = array_map([__CLASS__, 'csv_unsafe'], $r);
        }
        fclose($h);
        if (count($rows) < 1) wp_die(esc_html__('CSV vuoto.', 'vento-multilingua'));
        $header = array_map(function ($c) {
            return strtolower(trim((string) $c));
        }, array_shift($rows));
        return [$header, $rows];
    }

    public static function export_strings()
    {
        self::guard('vml_export_strings');
        $langs = VML_Settings::extra_languages();
        $all = [];
        foreach ($langs as $l) {
            foreach (VML_Strings::get($l) as $o => $t) $all[$o][$l] = $t;
        }
        $rows = [array_merge(['original'], $langs)];
        foreach ($all as $o => $tr) {
            $row = [$o];
            foreach ($langs as $l) $row[] = isset($tr[$l]) ? $tr[$l] : '';
            $rows[] = $row;
        }
        self::send_csv('traduzioni-testi-' . gmdate('Y-m-d') . '.csv', $rows);
    }

    public static function import_strings()
    {
        self::guard('vml_import_strings');
        list($header, $rows) = self::read_csv();
        $oi = array_search('original', $header, true);
        if ($oi === false) $oi = 0;
        $replace = !empty($_POST['replace']);
        $count = 0;
        foreach (VML_Settings::extra_languages() as $l) {
            $li = array_search($l, $header, true);
            if ($li === false) continue;
            $all = $replace ? [] : VML_Strings::get($l);
            foreach ($rows as $r) {
                $o = isset($r[$oi]) ? sanitize_text_field($r[$oi]) : '';
                $t = isset($r[$li]) ? sanitize_text_field($r[$li]) : '';
                if ($o === '' || $t === '') continue;
                $all[$o] = $t;
                $count++;
            }
            VML_Strings::save_all($l, $all);
        }
        self::back('vml-tools', sprintf(__('%d traduzioni importate.', 'vento-multilingua'), $count));
    }

    public static function export_slugs()
    {
        self::guard('vml_export_slugs');
        $langs = VML_Settings::extra_languages();
        $rows = [array_merge(['type', 'id', 'title', 'slug'], $langs)];
        $posts = get_posts(['post_type' => VML_Meta::post_types(), 'post_status' => ['publish', 'draft', 'private', 'pending', 'future'], 'numberposts' => -1, 'orderby' => 'type', 'order' => 'ASC']);
        foreach ($posts as $p) {
            $row = [$p->post_type, $p->ID, $p->post_title, urldecode($p->post_name)];
            foreach ($langs as $l) $row[] = urldecode((string) get_post_meta($p->ID, '_vml_slug_' . $l, true));
            $rows[] = $row;
        }
        foreach (VML_Meta::taxonomies() as $tx) {
            $terms = get_terms(['taxonomy' => $tx, 'hide_empty' => false]);
            if (is_wp_error($terms)) continue;
            foreach ($terms as $t) {
                $row = ['tax:' . $tx, $t->term_id, $t->name, urldecode($t->slug)];
                foreach ($langs as $l) $row[] = urldecode((string) get_term_meta($t->term_id, '_vml_slug_' . $l, true));
                $rows[] = $row;
            }
        }
        self::send_csv('slug-tradotti-' . gmdate('Y-m-d') . '.csv', $rows);
    }

    public static function import_slugs()
    {
        self::guard('vml_import_slugs');
        list($header, $rows) = self::read_csv();
        $ti = array_search('type', $header, true);
        $ii = array_search('id', $header, true);
        if ($ti === false || $ii === false) wp_die(esc_html__('Il CSV deve avere le colonne "type" e "id".', 'vento-multilingua'));
        $count = 0;
        foreach (VML_Settings::extra_languages() as $l) {
            $li = array_search($l, $header, true);
            if ($li === false) continue;
            foreach ($rows as $r) {
                if (!isset($r[$ti], $r[$ii], $r[$li])) continue;
                $type = trim($r[$ti]);
                $id = (int) $r[$ii];
                if (!$id) continue;
                if (strpos($type, 'tax:') === 0) {
                    $tx = substr($type, 4);
                    $term = get_term($id, $tx);
                    if (!$term || is_wp_error($term) || !in_array($tx, VML_Meta::taxonomies(), true)) continue;
                    VML_Slugs::save_term_slug($id, $tx, $l, $r[$li]);
                } else {
                    if (get_post_type($id) !== $type || !current_user_can('edit_post', $id)) continue;
                    VML_Slugs::save_post_slug($id, $l, $r[$li]);
                }
                $count++;
            }
        }
        VML_Slugs::flush();
        self::back('vml-tools', sprintf(__('%d slug elaborati.', 'vento-multilingua'), $count));
    }
}
