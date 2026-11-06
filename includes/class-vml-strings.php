<?php
if (!defined('ABSPATH')) exit;

/**
 * Dizionario delle correzioni manuali: testo originale (lingua base) => traduzione, una opzione per lingua.
 */
class VML_Strings
{
    const MAX_LEN = 2000; // caratteri massimi per testo originale/traduzione

    public static function option_name($lang)
    {
        return 'vml_strings_' . sanitize_key($lang);
    }

    public static function normalize($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}]+/u', ' ', $text);
        return trim((string) $text);
    }

    public static function get($lang)
    {
        static $cache = [];
        if (!isset($cache[$lang])) {
            $v = get_option(self::option_name($lang), []);
            $cache[$lang] = is_array($v) ? $v : [];
        }
        return $cache[$lang];
    }

    public static function save_all($lang, array $strings)
    {
        $clean = [];
        foreach ($strings as $o => $t) {
            $o = self::normalize($o);
            $t = self::normalize($t);
            if ($o === '' || $t === '' || $o === $t) continue;
            $clean[$o] = $t;
        }
        update_option(self::option_name($lang), $clean, false);
        return $clean;
    }

    public static function set($lang, $original, $translation)
    {
        $all = get_option(self::option_name($lang), []);
        if (!is_array($all)) $all = [];
        $o = self::normalize($original);
        $t = self::normalize($translation);
        if ($o === '' || $t === '' || mb_strlen($o) > self::MAX_LEN || mb_strlen($t) > self::MAX_LEN) return false;
        $all[$o] = $t;
        update_option(self::option_name($lang), $all, false);
        return true;
    }

    public static function delete($lang, $original)
    {
        $all = get_option(self::option_name($lang), []);
        if (!is_array($all)) return false;
        $o = self::normalize($original);
        unset($all[$o]);
        update_option(self::option_name($lang), $all, false);
        return true;
    }

    /**
     * Regex unica (alternativa ordinata per lunghezza) per trovare le stringhe nel testo.
     * Gli spazi della chiave corrispondono a qualsiasi spazio bianco; i confini evitano match dentro le parole.
     */
    public static function regex($lang)
    {
        $strings = self::get($lang);
        if (!$strings) return null;
        $keys = array_keys($strings);
        usort($keys, function ($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });
        $parts = [];
        foreach ($keys as $k) {
            $q = preg_quote($k, '/');
            $parts[] = str_replace(' ', '[\s\x{00A0}]+', $q);
        }
        return '/(?<![\p{L}\p{N}])(' . implode('|', $parts) . ')(?![\p{L}\p{N}])/u';
    }
}
