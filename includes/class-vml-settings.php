<?php
if (!defined('ABSPATH')) exit;

class VML_Settings
{
    const OPTION = 'vml_settings';

    public static function defaults()
    {
        $locale = function_exists('get_locale') ? strtolower(substr(get_locale(), 0, 2)) : 'it';
        if (!VML_Languages::exists($locale)) $locale = 'it';

        return [
            'default_lang'   => $locale,
            'languages'      => array_values(array_diff(['en', 'de', 'fr'], [$locale])),
            'auto_translate' => 1,       // traduzione Google nel browser
            'preload_hide'   => 1,       // nasconde la pagina finché la traduzione non è pronta
            'preload_max'    => 4,       // secondi massimi di attesa
            'float_switcher' => 1,       // selettore lingue fluttuante
            'float_position' => 'bottom-right',
            'switcher_style' => 'dropdown',
            'show_flags'     => 1,
            'capability'     => 'manage_options',
        ];
    }

    public static function activate()
    {
        if (false === get_option(self::OPTION, false)) {
            add_option(self::OPTION, self::defaults());
        }
        delete_transient('vml_slug_maps');
    }

    public static function get($key = null)
    {
        static $cache = null;
        if ($cache === null || $key === '__reset') {
            $saved = get_option(self::OPTION, []);
            $cache = array_merge(self::defaults(), is_array($saved) ? $saved : []);
            if ($key === '__reset') return $cache;
        }
        if ($key === null) return $cache;
        return isset($cache[$key]) ? $cache[$key] : null;
    }

    public static function save(array $data)
    {
        update_option(self::OPTION, $data);
        self::get('__reset');
        delete_transient('vml_slug_maps');
    }

    public static function default_lang()
    {
        return self::get('default_lang');
    }

    /** Lingue aggiuntive (senza la lingua base). */
    public static function extra_languages()
    {
        $default = self::default_lang();
        $langs = (array) self::get('languages');
        return array_values(array_filter($langs, function ($l) use ($default) {
            return $l !== $default && VML_Languages::exists($l);
        }));
    }

    /** Tutte le lingue attive, base per prima. */
    public static function all_languages()
    {
        return array_merge([self::default_lang()], self::extra_languages());
    }

    public static function is_extra($lang)
    {
        return in_array($lang, self::extra_languages(), true);
    }

    public static function capability()
    {
        return apply_filters('vml_capability', self::get('capability') ?: 'manage_options');
    }
}
