<?php
if (!defined('ABSPATH')) exit;

/**
 * Riconosce il prefisso lingua (/en/...), traduce lo slug richiesto nel percorso originale
 * e lascia a WordPress il normale parsing della richiesta.
 */
class VML_Router
{
    private static $lang = null;
    private static $current_original = null; // percorso originale + query della richiesta corrente
    private static $request_rel = null;       // percorso richiesto senza prefisso lingua

    public static function init()
    {
        self::detect();
        add_filter('do_parse_request', [__CLASS__, 'parse_request'], 1, 1);
        add_filter('redirect_canonical', [__CLASS__, 'redirect_canonical'], 10, 2);
    }

    public static function home_path()
    {
        static $p = null;
        if ($p === null) {
            $p = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
            $p = '/' . trim($p, '/') . '/';
            if ($p === '//') $p = '/';
        }
        return $p;
    }

    public static function lang()
    {
        return self::$lang ?: VML_Settings::default_lang();
    }

    public static function is_default()
    {
        return self::lang() === VML_Settings::default_lang();
    }

    public static function current_original()
    {
        if (self::$current_original === null) {
            $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
            $q = (string) wp_parse_url($uri, PHP_URL_QUERY);
            self::$current_original = VML_Slugs::rel_path($uri) . ($q !== '' ? '?' . $q : '');
        }
        return self::$current_original;
    }

    /** Il contesto non va gestito (admin, REST, cron, CLI...). */
    public static function skip_context()
    {
        if (is_admin() && !wp_doing_ajax()) return true;
        if (wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) return true;
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if (strpos($uri, '/wp-json/') !== false || isset($_GET['rest_route'])) return true;
        return false;
    }

    private static function detect()
    {
        self::$lang = VML_Settings::default_lang();
        if (self::skip_context() || wp_doing_ajax()) return;

        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = (string) wp_parse_url($uri, PHP_URL_PATH);
        $home = self::home_path();
        if (strpos($path, $home) !== 0 && rtrim($path, '/') . '/' !== $home) return;
        $rel = ltrim(substr($path, strlen($home)), '/');
        $first = strtolower(strtok($rel, '/'));
        if ($first !== false && VML_Settings::is_extra($first)) {
            self::$lang = $first;
        }
    }

    /** Eseguito all'inizio di WP::parse_request: riscrive REQUEST_URI nel percorso originale. */
    public static function parse_request($do)
    {
        if (self::skip_context()) return $do;

        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = (string) wp_parse_url($uri, PHP_URL_PATH);
        $query = (string) wp_parse_url($uri, PHP_URL_QUERY);
        $qs = $query !== '' ? '?' . $query : '';
        $home = self::home_path();
        if (strpos($path, $home) !== 0) return $do;
        $rel = '/' . ltrim(substr($path, strlen($home)), '/');

        $default = VML_Settings::default_lang();
        $segs = explode('/', trim($rel, '/'));
        $first = strtolower($segs[0]);

        // /it/... con lingua base => redirect senza prefisso.
        if ($first === $default && $first !== '') {
            $rest = '/' . implode('/', array_slice($segs, 1));
            wp_safe_redirect(home_url(self::join($rest, substr($path, -1) === '/')) . $qs, 301);
            exit;
        }

        if (self::is_default()) {
            self::$current_original = VML_Slugs::rel_path($path) . $qs;
            return $do;
        }

        $lang = self::lang();
        $rest_segs = array_slice($segs, 1);
        if (!$rest_segs && substr($path, -1) !== '/') {
            wp_safe_redirect(home_url('/' . $lang . '/') . $qs, 301);
            exit;
        }
        $rest = '/' . implode('/', $rest_segs);
        if ($rest !== '/' && substr($path, -1) === '/') $rest .= '/';

        // Slash finale coerente con la struttura dei permalink (WP lo farebbe con redirect_canonical, qui disattivato).
        $last = end($rest_segs);
        if ($rest !== '/' && substr($path, -1) !== '/' && $last !== false && strpos($last, '.') === false
            && substr((string) get_option('permalink_structure'), -1) === '/' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            wp_safe_redirect(home_url('/' . $lang . $rest . '/') . $qs, 301);
            exit;
        }
        self::$request_rel = $rest;

        $original = VML_Slugs::to_original($rest, $lang);

        // Slug originale usato con prefisso lingua ma esiste la versione tradotta => 301.
        if ($original === $rest) {
            $translated = VML_Slugs::to_translated($rest, $lang);
            if ($translated !== $rest) {
                wp_safe_redirect(home_url('/' . $lang . $translated) . $qs, 301);
                exit;
            }
        }

        self::$current_original = $original . $qs;
        $_SERVER['REQUEST_URI'] = rtrim($home, '/') . $original . $qs;
        return $do;
    }

    private static function join($path, $trailing)
    {
        $p = '/' . trim($path, '/');
        if ($p !== '/' && $trailing) $p .= '/';
        return $p;
    }

    /** Nelle lingue aggiuntive i redirect canonici di WP userebbero URL senza prefisso. */
    public static function redirect_canonical($redirect, $requested)
    {
        if (!self::is_default()) return false;
        return $redirect;
    }

    /**
     * URL di un percorso originale ("/chi-siamo/?a=b") nella lingua indicata.
     */
    public static function url_for($original, $lang)
    {
        $path = (string) wp_parse_url('http://x' . $original, PHP_URL_PATH);
        $query = (string) wp_parse_url('http://x' . $original, PHP_URL_QUERY);
        if ($path === '') $path = '/';
        if ($lang === VML_Settings::default_lang() || !VML_Settings::is_extra($lang)) {
            $url = home_url($path);
        } else {
            $url = home_url('/' . $lang . VML_Slugs::to_translated($path, $lang));
        }
        return $url . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Riscrive un URL interno nella lingua indicata. Restituisce l'URL invariato se esterno o non pertinente.
     */
    public static function localize_url($url, $lang)
    {
        $url = trim((string) $url);
        if ($url === '' || $url[0] === '#' || preg_match('#^(mailto|tel|javascript|data|sms|whatsapp):#i', $url)) return $url;

        $parts = wp_parse_url($url);
        if ($parts === false) return $url;
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!empty($parts['host']) && strcasecmp($parts['host'], $home_host) !== 0) return $url;
        if (empty($parts['host']) && (!isset($parts['path']) || $parts['path'] === '' || $parts['path'][0] !== '/')) {
            // URL relativo senza slash iniziale o solo query: lasciamo stare.
            return $url;
        }

        $path = isset($parts['path']) ? $parts['path'] : '/';
        $home = self::home_path();
        if (strpos($path, $home) !== 0 && rtrim($path, '/') . '/' !== $home) return $url;
        $rel = '/' . ltrim(substr($path, strlen($home)), '/');
        $segs = explode('/', trim($rel, '/'));
        $first = strtolower($segs[0]);

        $skip = ['wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'wp-login.php', 'xmlrpc.php', 'wp-cron.php', 'feed', 'wp-sitemap.xml'];
        if (in_array($first, $skip, true)) return $url;
        if (VML_Languages::exists($first)) return $url; // già con prefisso
        $last = end($segs);
        if ($last !== false && preg_match('/\.[a-z0-9]{2,5}$/i', $last)) return $url; // file

        $new_path = rtrim($home, '/') . '/' . $lang . VML_Slugs::to_translated($rel, $lang);

        $out = '';
        if (!empty($parts['scheme'])) $out .= $parts['scheme'] . ':';
        if (!empty($parts['host'])) {
            $out .= '//' . $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');
        }
        $out .= $new_path;
        if (isset($parts['query'])) $out .= '?' . $parts['query'];
        if (isset($parts['fragment'])) $out .= '#' . $parts['fragment'];
        return $out;
    }
}
