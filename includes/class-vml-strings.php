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

    /** Chiave di confronto: spazi normalizzati e minuscole (il confronto non distingue maiuscole/minuscole). */
    public static function fold($text)
    {
        return mb_strtolower(preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $text), 'UTF-8');
    }

    /** Rimuove le varianti che differiscono solo per maiuscole/minuscole, tenendo l'ultima. */
    private static function dedupe(array $all)
    {
        $out = [];
        $seen = [];
        foreach ($all as $o => $t) {
            $f = self::fold($o);
            if (isset($seen[$f])) unset($out[$seen[$f]]);
            $seen[$f] = $o;
            $out[$o] = $t;
        }
        return $out;
    }

    /**
     * Adatta le maiuscole della traduzione a quelle del testo trovato:
     * tutto maiuscolo -> tutto maiuscolo; iniziale maiuscola -> iniziale maiuscola.
     */
    public static function match_case($found, $translation)
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $found);
        if ($letters !== '' && mb_strlen($letters) > 1 && $letters === mb_strtoupper($letters, 'UTF-8')) {
            return mb_strtoupper($translation, 'UTF-8');
        }
        $first = mb_substr(ltrim($found), 0, 1, 'UTF-8');
        if ($first !== '' && $first === mb_strtoupper($first, 'UTF-8') && $first !== mb_strtolower($first, 'UTF-8')) {
            return mb_strtoupper(mb_substr($translation, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($translation, 1, null, 'UTF-8');
        }
        return $translation;
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
        $clean = self::dedupe($clean);
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
        unset($all[$o]);
        $all[$o] = $t;
        $all = self::dedupe($all);
        update_option(self::option_name($lang), $all, false);
        return true;
    }

    public static function delete($lang, $original)
    {
        $all = get_option(self::option_name($lang), []);
        if (!is_array($all)) return false;
        $f = self::fold(self::normalize($original));
        foreach (array_keys($all) as $k) {
            if (self::fold($k) === $f) unset($all[$k]);
        }
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
        return '/(?<![\p{L}\p{N}])(' . implode('|', $parts) . ')(?![\p{L}\p{N}])/iu';
    }
}
