<?php
if (!defined('ABSPATH')) exit;

/**
 * Frontend: HTML tradotto lato server (correzioni manuali + link localizzati), SEO, selettore lingue,
 * caricamento della traduzione automatica Google nel browser.
 */
class VML_Frontend
{
    public static function init()
    {
        add_action('template_redirect', [__CLASS__, 'start_buffer'], 99);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_head', [__CLASS__, 'preload'], 0);
        add_action('wp_head', [__CLASS__, 'head'], 1);
        add_action('wp_footer', [__CLASS__, 'footer'], 50);
        add_filter('language_attributes', [__CLASS__, 'language_attributes'], 20);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_filter('get_canonical_url', [__CLASS__, 'canonical'], 20, 1);
        add_filter('pre_get_document_title', [__CLASS__, 'document_title'], 20);
        add_shortcode('vml_switcher', [__CLASS__, 'shortcode']);

        // Inserimento automatico nell'header.
        add_filter('wp_nav_menu_items', [__CLASS__, 'menu_item'], 20, 2);
        add_filter('render_block_core/navigation', [__CLASS__, 'navigation_block'], 20, 2);

        // Compatibilità plugin SEO.
        add_filter('wpseo_title', [__CLASS__, 'seo_title'], 20);
        add_filter('wpseo_metadesc', [__CLASS__, 'seo_desc'], 20);
        add_filter('wpseo_opengraph_title', [__CLASS__, 'seo_title'], 20);
        add_filter('wpseo_opengraph_desc', [__CLASS__, 'seo_desc'], 20);
        add_filter('rank_math/frontend/title', [__CLASS__, 'seo_title'], 20);
        add_filter('rank_math/frontend/description', [__CLASS__, 'seo_desc'], 20);
        // Yoast/Rank Math: canonical e og:url devono puntare all'URL della lingua, non a quello italiano.
        add_filter('wpseo_canonical', [__CLASS__, 'canonical'], 20);
        add_filter('wpseo_opengraph_url', [__CLASS__, 'canonical'], 20);
        add_filter('rank_math/frontend/canonical', [__CLASS__, 'canonical'], 20);
        add_filter('wpseo_locale', [__CLASS__, 'og_locale'], 20);
        add_filter('wpseo_schema_webpage', [__CLASS__, 'schema_webpage'], 20);
    }

    /** Contesti in cui il frontend non va toccato (builder, anteprime, feed...). */
    public static function is_excluded()
    {
        if (VML_Router::skip_context() || wp_doing_ajax() || is_feed() || is_robots() || is_embed()) return true;
        if (is_customize_preview()) return true;
        foreach (['elementor-preview', 'et_fb', 'fl_builder', 'bricks', 'ct_builder', 'vc_editable', 'brizy-edit-iframe', 'breakdance'] as $flag) {
            if (isset($_GET[$flag])) return true; // phpcs:ignore WordPress.Security.NonceVerification
        }
        if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->preview) && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
            return true;
        }
        return (bool) apply_filters('vml_is_excluded', false);
    }

    public static function edit_mode()
    {
        return !VML_Router::is_default()
            && isset($_GET['vml_edit']) // phpcs:ignore WordPress.Security.NonceVerification
            && current_user_can(VML_Settings::capability());
    }

    /* ------------------------------------------------------------
     * Buffer HTML
     * ------------------------------------------------------------ */

    public static function start_buffer()
    {
        if (VML_Router::is_default() || self::is_excluded()) return;
        ob_start([__CLASS__, 'process_html']);
    }

    public static function process_html($html)
    {
        if (!is_string($html) || $html === '' || stripos($html, '<html') === false) return $html;
        $source = $html;
        $lang = VML_Router::lang();
        $edit = self::edit_mode();
        $regex = VML_Strings::regex($lang);
        $strings = $regex ? VML_Strings::get($lang) : [];
        $lookup = [];
        foreach ($strings as $k => $v) $lookup[preg_replace('/[\s\x{00A0}]+/u', ' ', $k)] = $v;

        // 1) Proteggi blocchi il cui contenuto non è testo visibile.
        $protected = [];
        $html = preg_replace_callback('#<(script|style|textarea|template|svg|noscript)\b[^>]*>.*?</\1\s*>#is', function ($m) use (&$protected) {
            $protected[] = $m[0];
            return "\x01VML" . (count($protected) - 1) . "\x01";
        }, $html);
        if ($html === null) return $source;

        // 2) Scorri tag e testo.
        $tokens = preg_split('/(<!--.*?-->|<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) return $source;
        $no_wrap = 0; // dentro <title>, <option>: niente span
        foreach ($tokens as $i => $tok) {
            if ($tok === '') continue;
            if ($i % 2 === 1) { // tag o commento
                if (preg_match('#^<(/?)(title|option)\b#i', $tok, $m)) {
                    $no_wrap = $m[1] === '/' ? max(0, $no_wrap - 1) : $no_wrap + 1;
                } elseif (preg_match('#^<(a|form)\s#i', $tok)) {
                    $tokens[$i] = self::rewrite_tag($tok, $lang, $edit);
                }
                continue;
            }
            if ($regex && strpos($tok, "\x01VML") === false) {
                $tokens[$i] = self::translate_text($tok, $regex, $lookup, $no_wrap === 0, $edit);
            } elseif ($regex) {
                // testo che contiene segnaposto: traduci i pezzi fra i segnaposto
                $pieces = preg_split("/(\x01VML\d+\x01)/", $tok, -1, PREG_SPLIT_DELIM_CAPTURE);
                foreach ($pieces as $j => $p) {
                    if ($j % 2 === 0) $pieces[$j] = self::translate_text($p, $regex, $lookup, $no_wrap === 0, $edit);
                }
                $tokens[$i] = implode('', $pieces);
            }
        }
        $html = implode('', $tokens);

        // 3) Ripristina i blocchi protetti.
        $html = preg_replace_callback("/\x01VML(\d+)\x01/", function ($m) use ($protected) {
            return isset($protected[(int) $m[1]]) ? $protected[(int) $m[1]] : '';
        }, $html);

        return $html;
    }

    private static function translate_text($text, $regex, array $lookup, $wrap, $edit)
    {
        if (trim($text) === '') return $text;
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!@preg_match($regex, $decoded)) return $text;
        $parts = preg_split($regex, $decoded, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) return $text;
        $out = '';
        foreach ($parts as $j => $p) {
            if ($j % 2 === 0) {
                $out .= htmlspecialchars($p, ENT_NOQUOTES, 'UTF-8', false);
                continue;
            }
            $key = preg_replace('/[\s\x{00A0}]+/u', ' ', $p);
            if (!isset($lookup[$key])) {
                $out .= htmlspecialchars($p, ENT_NOQUOTES, 'UTF-8', false);
                continue;
            }
            $t = esc_html($lookup[$key]);
            if ($wrap) {
                $out .= '<span class="notranslate vml-t" translate="no"' . ($edit ? ' data-vml-o="' . esc_attr($key) . '"' : '') . '>' . $t . '</span>';
            } else {
                $out .= $t;
            }
        }
        return $out;
    }

    private static function rewrite_tag($tag, $lang, $edit)
    {
        if (stripos($tag, 'hreflang=') !== false || stripos($tag, 'data-vml-skip') !== false) return $tag;
        return preg_replace_callback('#(\s(?:href|action)\s*=\s*)(["\'])(.*?)\2#is', function ($m) use ($lang, $edit) {
            $url = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $new = VML_Router::localize_url($url, $lang);
            if ($new === $url) return $m[0];
            if ($edit && strpos($new, 'vml_edit') === false) $new = add_query_arg('vml_edit', '1', $new);
            return $m[1] . $m[2] . esc_attr($new) . $m[2];
        }, $tag);
    }

    /* ------------------------------------------------------------
     * Head, SEO
     * ------------------------------------------------------------ */

    /** La traduzione Google è attiva su questa pagina. */
    public static function google_active()
    {
        return !VML_Router::is_default() && VML_Settings::get('auto_translate') && !self::edit_mode() && !self::is_excluded();
    }

    /**
     * Anti-flash: nasconde il contenuto (con un indicatore di caricamento) finché Google non ha tradotto,
     * con un tempo massimo di sicurezza. Stampato per primo nell'head, prima che la pagina venga disegnata.
     */
    public static function preload()
    {
        if (!self::google_active() || !VML_Settings::get('preload_hide')) return;
        $max = (int) round(1000 * (float) (VML_Settings::get('preload_max') ?: 4));
        ?>
<style id="vml-preload">
html.vml-loading body{opacity:0!important}
html.vml-loading::before{content:"";position:fixed;top:50%;left:50%;width:34px;height:34px;margin:-17px 0 0 -17px;border:3px solid rgba(0,0,0,.12);border-top-color:rgba(0,0,0,.55);border-radius:50%;animation:vml-spin .8s linear infinite;z-index:2147483647}
html.vml-ready body{transition:opacity .2s ease-in}
@keyframes vml-spin{to{transform:rotate(360deg)}}
</style>
<script>(function(d){var h=d.documentElement;h.classList.add('vml-loading');window.vmlReveal=function(){h.classList.add('vml-ready');h.classList.remove('vml-loading');};setTimeout(window.vmlReveal,<?php echo (int) $max; ?>);})(document);</script>
<?php
    }

    public static function head()
    {
        if (self::is_excluded() || is_404()) {
            return;
        }
        $orig = VML_Router::current_original();
        $default = VML_Settings::default_lang();
        echo "\n<!-- Vento Multilingua -->\n";
        foreach (VML_Settings::all_languages() as $l) {
            printf('<link rel="alternate" hreflang="%s" href="%s" />' . "\n", esc_attr(VML_Languages::hreflang($l)), esc_url(VML_Router::url_for($orig, $l)));
        }
        printf('<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url(VML_Router::url_for($orig, $default)));

        if (!VML_Router::is_default() && !self::has_seo_plugin()) {
            $desc = self::meta_for_current('desc');
            if ($desc !== '') printf('<meta name="description" content="%s" />' . "\n", esc_attr($desc));
        }
    }

    private static function has_seo_plugin()
    {
        return defined('WPSEO_VERSION') || class_exists('RankMath') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION');
    }

    /** Meta tradotta (title|desc) dell'oggetto corrente. */
    public static function meta_for_current($what)
    {
        $lang = VML_Router::lang();
        $key = '_vml_' . $what . '_' . $lang;
        if (is_singular() || (is_front_page() && get_option('page_on_front')) || (is_home() && get_option('page_for_posts'))) {
            $id = get_queried_object_id();
            if (!$id && is_front_page()) $id = (int) get_option('page_on_front');
            return $id ? (string) get_post_meta($id, $key, true) : '';
        }
        if (is_category() || is_tag() || is_tax()) {
            return (string) get_term_meta(get_queried_object_id(), $key, true);
        }
        return '';
    }

    public static function document_title($title)
    {
        if (VML_Router::is_default() || self::is_excluded()) return $title;
        $t = self::meta_for_current('title');
        return $t !== '' ? $t : $title;
    }

    public static function seo_title($title)
    {
        if (VML_Router::is_default()) return $title;
        $t = self::meta_for_current('title');
        return $t !== '' ? $t : $title;
    }

    public static function seo_desc($desc)
    {
        if (VML_Router::is_default()) return $desc;
        $d = self::meta_for_current('desc');
        return $d !== '' ? $d : $desc;
    }

    public static function canonical($url)
    {
        if (VML_Router::is_default() || !$url) return $url;
        $q = (string) wp_parse_url($url, PHP_URL_QUERY);
        return VML_Router::url_for(VML_Slugs::rel_path($url) . ($q !== '' ? '?' . $q : ''), VML_Router::lang());
    }

    /** og:locale coerente con la lingua (es. en_GB, de_DE, fr_FR). */
    public static function og_locale($locale)
    {
        if (VML_Router::is_default()) return $locale;
        $map = apply_filters('vml_og_locales', ['en' => 'en_GB', 'de' => 'de_DE', 'fr' => 'fr_FR', 'es' => 'es_ES', 'pt' => 'pt_PT', 'nl' => 'nl_NL', 'pl' => 'pl_PL', 'ru' => 'ru_RU', 'ja' => 'ja_JP', 'zh-cn' => 'zh_CN', 'zh-tw' => 'zh_TW']);
        $l = VML_Router::lang();
        return isset($map[$l]) ? $map[$l] : $l;
    }

    /** Schema.org di Yoast: url e lingua della pagina tradotta. */
    public static function schema_webpage($data)
    {
        if (VML_Router::is_default() || !is_array($data)) return $data;
        if (!empty($data['url'])) $data['url'] = self::canonical($data['url']);
        $data['inLanguage'] = VML_Languages::hreflang(VML_Router::lang());
        return $data;
    }

    public static function language_attributes($output)
    {
        if (VML_Router::is_default() || is_admin()) return $output;
        $code = VML_Languages::hreflang(VML_Router::lang());
        if (preg_match('/lang="[^"]*"/', $output)) {
            return preg_replace('/lang="[^"]*"/', 'lang="' . esc_attr($code) . '"', $output);
        }
        return $output . ' lang="' . esc_attr($code) . '"';
    }

    public static function body_class($classes)
    {
        $classes[] = 'vml-lang-' . sanitize_html_class(VML_Router::lang());
        return $classes;
    }

    /* ------------------------------------------------------------
     * Asset, Google, selettore
     * ------------------------------------------------------------ */

    public static function enqueue()
    {
        if (self::is_excluded()) return;
        wp_enqueue_style('vml-front', VML_URL . 'assets/css/vml-front.css', [], VML_VERSION);
        wp_enqueue_script('vml-front', VML_URL . 'assets/js/vml-front.js', [], VML_VERSION, true);
        $lang = VML_Router::lang();
        $google = self::google_active();
        wp_localize_script('vml-front', 'vmlFront', [
            'lang'   => $lang,
            'google' => $google ? 1 : 0,
            'source' => VML_Languages::google_code(VML_Settings::default_lang()),
            'target' => VML_Languages::google_code($lang),
            'headerSelector' => VML_Settings::get('header_mode') === 'selector' ? (string) VML_Settings::get('header_selector') : '',
            'headerPosition' => VML_Settings::get('header_position') === 'prepend' ? 'prepend' : 'append',
        ]);
    }

    public static function footer()
    {
        if (self::is_excluded()) return;
        echo '<div id="vml-google-element" class="notranslate" translate="no" aria-hidden="true"></div>';
        if (VML_Settings::get('float_switcher')) {
            $pos = sanitize_html_class(VML_Settings::get('float_position') ?: 'bottom-right');
            echo '<div class="vml-float vml-float-' . esc_attr($pos) . '">' . self::render_switcher(['style' => 'dropdown', 'up' => strpos($pos, 'bottom') === 0]) . '</div>';
        }
        // Selettore da spostare nell'header tramite selettore CSS (vedi vml-front.js).
        if (VML_Settings::get('header_mode') === 'selector' && VML_Settings::get('header_selector')) {
            echo '<div id="vml-header-switcher" hidden>' . self::header_switcher() . '</div>';
        }
    }

    /* ---------------- Header ---------------- */

    private static function header_switcher($extra_class = '')
    {
        return self::render_switcher(['style' => VML_Settings::get('header_style') ?: 'dropdown', 'class' => 'vml-in-header ' . $extra_class]);
    }

    /** Voce aggiunta in fondo ai menu classici (anche widget menu di Elementor). */
    public static function menu_item($items, $args)
    {
        if (VML_Settings::get('header_mode') !== 'menu' || self::is_excluded() || is_admin()) return $items;
        $target = (string) VML_Settings::get('header_menu');
        if ($target !== 'all') {
            $menu = null;
            if (!empty($args->menu)) {
                $menu = is_object($args->menu) ? $args->menu : wp_get_nav_menu_object($args->menu);
            }
            if (!$menu && !empty($args->theme_location)) {
                $locations = get_nav_menu_locations();
                if (isset($locations[$args->theme_location])) $menu = wp_get_nav_menu_object($locations[$args->theme_location]);
            }
            if (!$menu || (string) $menu->term_id !== $target) return $items;
        }
        return $items . '<li class="menu-item vml-menu-item">' . self::header_switcher() . '</li>';
    }

    /** Blocco Navigazione dei temi a blocchi (es. Twenty Twenty-Five). */
    public static function navigation_block($html, $block)
    {
        if (VML_Settings::get('header_mode') !== 'menu' || VML_Settings::get('header_menu') !== 'all' || self::is_excluded()) return $html;
        $li = '<li class="wp-block-navigation-item vml-menu-item">' . self::header_switcher() . '</li>';
        $pos = strrpos($html, '</ul>');
        return $pos === false ? $html : substr_replace($html, $li . '</ul>', $pos, 5);
    }

    public static function shortcode($atts)
    {
        $atts = shortcode_atts(['style' => VML_Settings::get('switcher_style'), 'flags' => VML_Settings::get('show_flags')], $atts, 'vml_switcher');
        return self::render_switcher($atts);
    }

    /**
     * Selettore lingue. Stili: dropdown | flags | list | codes.
     */
    public static function render_switcher($args = [])
    {
        $args = wp_parse_args($args, [
            'style' => VML_Settings::get('switcher_style') ?: 'dropdown',
            'flags' => VML_Settings::get('show_flags'),
            'up'    => false,
            'class' => '',
        ]);
        $flags = filter_var($args['flags'], FILTER_VALIDATE_BOOLEAN) || $args['flags'] === 1 || $args['flags'] === '1';
        $current = VML_Router::lang();
        $orig = VML_Router::current_original();
        $edit = self::edit_mode();

        $item = function ($l, $label_mode) use ($orig, $current, $flags, $edit) {
            $url = VML_Router::url_for($orig, $l);
            if ($edit && $l !== VML_Settings::default_lang()) $url = add_query_arg('vml_edit', '1', $url);
            $label = $label_mode === 'code' ? strtoupper($l) : VML_Languages::native_name($l);
            $html = '<a href="' . esc_url($url) . '" hreflang="' . esc_attr(VML_Languages::hreflang($l)) . '" lang="' . esc_attr(VML_Languages::hreflang($l)) . '" class="vml-item' . ($l === $current ? ' vml-current' : '') . '"' . ($l === $current ? ' aria-current="true"' : '') . ' title="' . esc_attr(VML_Languages::native_name($l)) . '">';
            if ($flags || $label_mode === 'flag') $html .= VML_Languages::flag($l);
            if ($label_mode !== 'flag') $html .= '<span class="vml-label">' . esc_html($label) . '</span>';
            return $html . '</a>';
        };

        $langs = VML_Settings::all_languages();
        $style = in_array($args['style'], ['dropdown', 'flags', 'list', 'codes'], true) ? $args['style'] : 'dropdown';
        $out = '<div class="vml-switcher vml-style-' . $style . ($args['up'] ? ' vml-up' : '') . ($args['class'] ? ' ' . esc_attr(trim($args['class'])) : '') . ' notranslate" translate="no">';

        if ($style === 'dropdown') {
            $out .= '<button type="button" class="vml-toggle" aria-haspopup="true">' . ($flags ? VML_Languages::flag($current) : '') . '<span class="vml-label">' . esc_html(VML_Languages::native_name($current)) . '</span><span class="vml-caret" aria-hidden="true"></span></button><ul class="vml-menu">';
            foreach ($langs as $l) {
                if ($l === $current) continue;
                $out .= '<li>' . $item($l, 'name') . '</li>';
            }
            $out .= '</ul>';
        } else {
            $mode = $style === 'flags' ? 'flag' : ($style === 'codes' ? 'code' : 'name');
            $out .= '<ul class="vml-inline">';
            foreach ($langs as $l) $out .= '<li>' . $item($l, $mode) . '</li>';
            $out .= '</ul>';
        }
        return $out . '</div>';
    }
}
