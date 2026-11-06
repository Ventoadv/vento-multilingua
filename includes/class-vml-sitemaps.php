<?php
if (!defined('ABSPATH')) exit;

/**
 * Dati delle sitemap per lingua (comuni a sitemap native WP e Yoast) + integrazione Yoast SEO.
 * Tutto è generato a ogni richiesta: nessun file statico, gli URL usano il dominio del sito corrente.
 */
class VML_Sitemaps
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'yoast_register'], 20);
        add_filter('wpseo_sitemap_index', [__CLASS__, 'yoast_index']);
    }

    /* ---------------- Dati ---------------- */

    public static function post_types()
    {
        $types = get_post_types(['public' => true], 'objects');
        unset($types['attachment']);
        $types = apply_filters('wp_sitemaps_post_types', $types);
        $types = array_values(array_intersect(array_keys((array) $types), VML_Meta::post_types()));
        if (self::yoast_active()) {
            $types = array_values(array_filter($types, function ($pt) {
                return !class_exists('WPSEO_Post_Type') || WPSEO_Post_Type::is_post_type_indexable($pt);
            }));
        }
        return $types;
    }

    public static function taxonomies()
    {
        $tax = get_taxonomies(['public' => true], 'objects');
        $tax = array_filter($tax, 'is_taxonomy_viewable');
        $tax = apply_filters('wp_sitemaps_taxonomies', $tax);
        $tax = array_values(array_intersect(array_keys((array) $tax), VML_Meta::taxonomies()));
        if (self::yoast_active() && class_exists('WPSEO_Options')) {
            $tax = array_values(array_filter($tax, function ($t) {
                return !WPSEO_Options::get('noindex-tax-' . $t, false);
            }));
        }
        return $tax;
    }

    /** Sottotipi: "p-{post_type}" e "t-{tassonomia}". */
    public static function subtypes()
    {
        $out = [];
        foreach (self::post_types() as $pt) $out[] = 'p-' . $pt;
        foreach (self::taxonomies() as $tx) $out[] = 't-' . $tx;
        return $out;
    }

    public static function split($subtype)
    {
        $kind = substr((string) $subtype, 0, 2);
        $name = substr((string) $subtype, 2);
        if ($kind === 'p-' && in_array($name, self::post_types(), true)) return ['post', $name];
        if ($kind === 't-' && in_array($name, self::taxonomies(), true)) return ['term', $name];
        return [null, null];
    }

    private static function loc($url, $lang)
    {
        $q = (string) wp_parse_url($url, PHP_URL_QUERY);
        return VML_Router::url_for(VML_Slugs::rel_path($url) . ($q !== '' ? '?' . $q : ''), $lang);
    }

    /** Post esclusi in Yoast (noindex). */
    private static function yoast_noindex_post($post_id)
    {
        if (!self::yoast_active()) return false;
        return get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true) === '1';
    }

    /** Elenco URL: [['loc' => ..., 'lastmod' => ...], ...] */
    public static function url_list($lang, $subtype, $page, $per_page)
    {
        list($kind, $name) = self::split($subtype);
        $page = max(1, (int) $page);
        $list = [];

        if ($kind === 'post') {
            if ($name === 'page' && $page === 1) {
                $list[] = ['loc' => VML_Router::url_for('/', $lang)];
            }
            $q = new WP_Query([
                'post_type'              => $name,
                'post_status'            => 'publish',
                'posts_per_page'         => $per_page,
                'paged'                  => $page,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'has_password'           => false,
                'update_post_term_cache' => false,
            ]);
            $front = (int) get_option('page_on_front');
            foreach ($q->posts as $post) {
                if ($post->ID === $front || self::yoast_noindex_post($post->ID)) continue;
                $entry = ['loc' => self::loc(get_permalink($post), $lang)];
                if ($post->post_modified_gmt && $post->post_modified_gmt !== '0000-00-00 00:00:00') {
                    $entry['lastmod'] = gmdate('c', strtotime($post->post_modified_gmt . ' UTC'));
                }
                $list[] = apply_filters('vml_sitemap_entry', $entry, $post, $lang);
            }
        } elseif ($kind === 'term') {
            $terms = get_terms(['taxonomy' => $name, 'hide_empty' => true, 'number' => $per_page, 'offset' => ($page - 1) * $per_page]);
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $link = get_term_link($term);
                    if (is_wp_error($link)) continue;
                    $list[] = apply_filters('vml_sitemap_entry', ['loc' => self::loc($link, $lang)], $term, $lang);
                }
            }
        }
        return $list;
    }

    public static function max_pages($subtype, $per_page)
    {
        list($kind, $name) = self::split($subtype);
        if ($kind === 'post') {
            $counts = wp_count_posts($name);
            $total = isset($counts->publish) ? (int) $counts->publish : 0;
            if ($name === 'page') $total++;
            return $total > 0 ? (int) ceil($total / $per_page) : 0;
        }
        if ($kind === 'term') {
            $total = (int) wp_count_terms(['taxonomy' => $name, 'hide_empty' => true]);
            return $total > 0 ? (int) ceil($total / $per_page) : 0;
        }
        return 0;
    }

    /* ---------------- Yoast SEO ---------------- */

    public static function yoast_active()
    {
        return defined('WPSEO_VERSION');
    }

    private static function yoast_per_page()
    {
        return max(1, (int) apply_filters('wpseo_sitemap_entries_per_page', 1000));
    }

    /** Nome sitemap Yoast: "vml-en-p-page" => /vml-en-p-page-sitemap.xml */
    private static function yoast_name($lang, $subtype)
    {
        return 'vml-' . $lang . '-' . $subtype;
    }

    public static function yoast_register()
    {
        global $wpseo_sitemaps;
        if (!self::yoast_active() || !is_object($wpseo_sitemaps) || !method_exists($wpseo_sitemaps, 'register_sitemap')) return;
        if (!apply_filters('vml_sitemaps_enabled', true)) return;

        foreach (VML_Settings::extra_languages() as $lang) {
            foreach (self::subtypes() as $sub) {
                $wpseo_sitemaps->register_sitemap(self::yoast_name($lang, $sub), function () use ($lang, $sub) {
                    self::yoast_render($lang, $sub);
                });
            }
        }

        // Le nuove regole di riscrittura di Yoast vanno registrate una volta quando cambiano lingue/tipi.
        $sig = md5(VML_VERSION . '|' . implode(',', VML_Settings::extra_languages()) . '|' . implode(',', self::subtypes()));
        if (get_option('vml_yoast_rewrite_sig') !== $sig) {
            update_option('vml_yoast_rewrite_sig', $sig, false);
            flush_rewrite_rules(false);
        }
    }

    private static function yoast_render($lang, $sub)
    {
        global $wpseo_sitemaps;
        $page = max(1, (int) get_query_var('sitemap_n'));
        $list = self::url_list($lang, $sub, $page, self::yoast_per_page());
        if (!$list) {
            $wpseo_sitemaps->bad_sitemap = true;
            return;
        }
        $xml = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($list as $e) {
            $xml .= "\t<url>\n\t\t<loc>" . htmlspecialchars($e['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if (!empty($e['lastmod'])) $xml .= "\t\t<lastmod>" . htmlspecialchars($e['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
            $xml .= "\t</url>\n";
        }
        $xml .= '</urlset>';
        $wpseo_sitemaps->set_sitemap($xml);
    }

    /** Aggiunge le sitemap per lingua all'indice di Yoast (/sitemap_index.xml). */
    public static function yoast_index($extra)
    {
        if (!apply_filters('vml_sitemaps_enabled', true)) return $extra;
        $per = self::yoast_per_page();
        $now = gmdate('c');
        foreach (VML_Settings::extra_languages() as $lang) {
            foreach (self::subtypes() as $sub) {
                $pages = self::max_pages($sub, $per);
                for ($i = 1; $i <= $pages; $i++) {
                    $loc = home_url('/' . self::yoast_name($lang, $sub) . '-sitemap' . ($i > 1 ? $i : '') . '.xml');
                    $extra .= "<sitemap>\n\t<loc>" . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n\t<lastmod>" . $now . "</lastmod>\n</sitemap>\n";
                }
            }
        }
        return $extra;
    }
}
