<?php
if (!defined('ABSPATH')) exit;

/**
 * Slug tradotti.
 *
 * Gli slug sono salvati in meta (_vml_slug_{lingua}) di post e termini. Da questi si costruisce,
 * per ogni lingua, una mappa "percorso originale => percorso tradotto" (e l'inversa) messa in cache.
 * La conversione usa il prefisso più lungo, quindi figli, paginazione e feed seguono il genitore tradotto.
 */
class VML_Slugs
{
    const TRANSIENT = 'vml_slug_maps';

    public static function init()
    {
        $flush = [__CLASS__, 'flush'];
        add_action('save_post', $flush);
        add_action('deleted_post', $flush);
        add_action('trashed_post', $flush);
        add_action('edited_term', $flush);
        add_action('delete_term', $flush);
        add_action('update_option_permalink_structure', $flush);
        add_action('update_option_page_on_front', $flush);
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta', 'added_term_meta', 'updated_term_meta', 'deleted_term_meta'] as $h) {
            add_action($h, function ($mid, $oid, $key) {
                if (is_string($key) && strpos($key, '_vml_slug_') === 0) self::flush();
            }, 10, 3);
        }
    }

    public static function flush()
    {
        delete_transient(self::TRANSIENT);
        self::$maps = null;
    }

    private static $maps = null;

    public static function meta_key($lang)
    {
        return '_vml_slug_' . $lang;
    }

    public static function get_maps()
    {
        if (self::$maps !== null) return self::$maps;
        $maps = get_transient(self::TRANSIENT);
        if (!is_array($maps)) {
            $maps = self::build_maps();
            set_transient(self::TRANSIENT, $maps, DAY_IN_SECONDS);
        }
        self::$maps = $maps;
        return $maps;
    }

    /** Percorso relativo alla home, normalizzato come "/a/b/". */
    public static function rel_path($url)
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $home = VML_Router::home_path();
        if ($home !== '/' && strpos($path, $home) === 0) {
            $path = substr($path, strlen($home));
        } elseif ($home !== '/' && rtrim($path, '/') === rtrim($home, '/')) {
            $path = '';
        }
        $path = trim($path, '/');
        return $path === '' ? '/' : '/' . $path . '/';
    }

    private static function build_maps()
    {
        global $wpdb;
        $langs = VML_Settings::extra_languages();
        $maps = [];
        foreach ($langs as $l) $maps[$l] = ['fwd' => [], 'rev' => []];
        if (!$langs) return $maps;

        $like = $wpdb->esc_like('_vml_slug_') . '%';

        // Post / pagine / CPT.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.post_id AS id, pm.meta_key AS k FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key LIKE %s AND pm.meta_value <> '' AND p.post_status = 'publish'",
            $like
        ));
        foreach ($rows as $r) {
            $lang = substr($r->k, strlen('_vml_slug_'));
            if (!isset($maps[$lang])) continue;
            $post = get_post((int) $r->id);
            if (!$post) continue;
            $orig = self::rel_path(get_permalink($post));
            if ($orig === '/') continue;
            $chain = [];
            if (is_post_type_hierarchical($post->post_type)) {
                foreach (array_reverse(get_post_ancestors($post)) as $aid) {
                    $chain[] = [get_post_field('post_name', $aid), get_post_meta($aid, self::meta_key($lang), true)];
                }
            }
            $chain[] = [$post->post_name, get_post_meta($post->ID, self::meta_key($lang), true)];
            self::add_to_map($maps[$lang], $orig, $chain);
        }

        // Termini (categorie, tag, tassonomie pubbliche).
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT term_id AS id, meta_key AS k FROM {$wpdb->termmeta} WHERE meta_key LIKE %s AND meta_value <> ''",
            $like
        ));
        foreach ($rows as $r) {
            $lang = substr($r->k, strlen('_vml_slug_'));
            if (!isset($maps[$lang])) continue;
            $term = get_term((int) $r->id);
            if (!$term || is_wp_error($term)) continue;
            $link = get_term_link($term);
            if (is_wp_error($link)) continue;
            $orig = self::rel_path($link);
            $chain = [];
            if (is_taxonomy_hierarchical($term->taxonomy)) {
                foreach (array_reverse(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy')) as $aid) {
                    $a = get_term($aid);
                    if ($a && !is_wp_error($a)) $chain[] = [$a->slug, get_term_meta($aid, self::meta_key($lang), true)];
                }
            }
            $chain[] = [$term->slug, get_term_meta($term->term_id, self::meta_key($lang), true)];
            self::add_to_map($maps[$lang], $orig, $chain);
        }

        return $maps;
    }

    /** Sostituisce gli ultimi segmenti del percorso originale con gli slug tradotti della catena. */
    private static function add_to_map(array &$map, $orig, array $chain)
    {
        $segs = explode('/', trim($orig, '/'));
        $n = count($chain);
        $offset = count($segs) - $n;
        foreach ($chain as $j => $pair) {
            $idx = $offset + $j;
            if ($idx < 0) continue;
            list($o, $t) = $pair;
            if ($t !== '' && $t !== null && rawurldecode($segs[$idx]) === rawurldecode($o)) {
                $segs[$idx] = $t;
            }
        }
        $trans = '/' . implode('/', $segs) . '/';
        if ($trans === $orig) return;
        if (isset($map['rev'][$trans]) || isset($map['fwd'][$orig])) return; // collisione: vince il primo
        $map['fwd'][$orig] = $trans;
        $map['rev'][$trans] = $orig;
    }

    /** Converte un percorso con la mappa usando il prefisso più lungo. */
    private static function convert($path, array $map)
    {
        if (!$map) return $path;
        $trail = substr($path, -1) === '/';
        $inner = trim($path, '/');
        if ($inner === '') return $path;
        $segs = explode('/', $inner);
        for ($i = count($segs); $i > 0; $i--) {
            $head = '/' . implode('/', array_slice($segs, 0, $i)) . '/';
            $key = isset($map[$head]) ? $head : (isset($map[strtolower($head)]) ? strtolower($head) : null);
            if ($key === null) continue;
            $rest = array_slice($segs, $i);
            $out = rtrim($map[$key], '/');
            if ($rest) $out .= '/' . implode('/', $rest);
            return $out . ($trail ? '/' : '');
        }
        return $path;
    }

    /** Percorso originale -> percorso tradotto nella lingua. */
    public static function to_translated($path, $lang)
    {
        $maps = self::get_maps();
        return isset($maps[$lang]) ? self::convert($path, $maps[$lang]['fwd']) : $path;
    }

    /** Percorso tradotto -> percorso originale. */
    public static function to_original($path, $lang)
    {
        $maps = self::get_maps();
        return isset($maps[$lang]) ? self::convert($path, $maps[$lang]['rev']) : $path;
    }

    /* ------------------------------------------------------------
     * Salvataggio con unicità
     * ------------------------------------------------------------ */

    public static function sanitize($slug)
    {
        return sanitize_title(wp_unslash((string) $slug));
    }

    public static function save_post_slug($post_id, $lang, $slug)
    {
        $slug = self::sanitize($slug);
        if ($slug === '') {
            delete_post_meta($post_id, self::meta_key($lang));
            return '';
        }
        global $wpdb;
        $post = get_post($post_id);
        if (!$post) return '';
        $base = $slug;
        $i = 2;
        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.ID <> %d AND p.post_parent = %d AND p.post_status NOT IN ('trash','auto-draft') LIMIT 1",
                self::meta_key($lang), $slug, $post_id, (int) $post->post_parent
            ));
            if (!$exists && $slug !== $post->post_name) {
                // Lo slug tradotto non deve coincidere con lo slug originale di un altro contenuto allo stesso livello,
                // altrimenti /lingua/slug diventerebbe ambiguo (es. pagina non tradotta con lo stesso slug).
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND ID <> %d AND post_parent = %d
                     AND post_status NOT IN ('trash','auto-draft','inherit') AND post_type NOT IN ('revision','nav_menu_item','attachment') LIMIT 1",
                    $slug, $post_id, (int) $post->post_parent
                ));
            }
            if (!$exists) break;
            $slug = $base . '-' . $i++;
        }
        update_post_meta($post_id, self::meta_key($lang), $slug);
        return $slug;
    }

    public static function save_term_slug($term_id, $taxonomy, $lang, $slug)
    {
        $slug = self::sanitize($slug);
        if ($slug === '') {
            delete_term_meta($term_id, self::meta_key($lang));
            return '';
        }
        global $wpdb;
        $base = $slug;
        $i = 2;
        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT tm.term_id FROM {$wpdb->termmeta} tm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
                 WHERE tm.meta_key = %s AND tm.meta_value = %s AND tm.term_id <> %d AND tt.taxonomy = %s LIMIT 1",
                self::meta_key($lang), $slug, $term_id, $taxonomy
            ));
            if (!$exists) {
                $other = get_term_by('slug', $slug, $taxonomy);
                if ($other && (int) $other->term_id !== (int) $term_id) $exists = $other->term_id;
            }
            if (!$exists) break;
            $slug = $base . '-' . $i++;
        }
        update_term_meta($term_id, self::meta_key($lang), $slug);
        return $slug;
    }
}
