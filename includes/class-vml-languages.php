<?php
if (!defined('ABSPATH')) exit;

/**
 * Elenco lingue supportate: codice URL => [nome nativo, nome italiano, codice Google, hreflang].
 */
class VML_Languages
{
    public static function all()
    {
        static $list = null;
        if ($list !== null) return $list;

        $list = [
            'it'    => ['Italiano', 'Italiano', 'it', 'it'],
            'en'    => ['English', 'Inglese', 'en', 'en'],
            'de'    => ['Deutsch', 'Tedesco', 'de', 'de'],
            'fr'    => ['Français', 'Francese', 'fr', 'fr'],
            'es'    => ['Español', 'Spagnolo', 'es', 'es'],
            'pt'    => ['Português', 'Portoghese', 'pt', 'pt'],
            'nl'    => ['Nederlands', 'Olandese', 'nl', 'nl'],
            'pl'    => ['Polski', 'Polacco', 'pl', 'pl'],
            'ro'    => ['Română', 'Rumeno', 'ro', 'ro'],
            'ru'    => ['Русский', 'Russo', 'ru', 'ru'],
            'uk'    => ['Українська', 'Ucraino', 'uk', 'uk'],
            'el'    => ['Ελληνικά', 'Greco', 'el', 'el'],
            'cs'    => ['Čeština', 'Ceco', 'cs', 'cs'],
            'sk'    => ['Slovenčina', 'Slovacco', 'sk', 'sk'],
            'sl'    => ['Slovenščina', 'Sloveno', 'sl', 'sl'],
            'hr'    => ['Hrvatski', 'Croato', 'hr', 'hr'],
            'hu'    => ['Magyar', 'Ungherese', 'hu', 'hu'],
            'bg'    => ['Български', 'Bulgaro', 'bg', 'bg'],
            'sv'    => ['Svenska', 'Svedese', 'sv', 'sv'],
            'da'    => ['Dansk', 'Danese', 'da', 'da'],
            'no'    => ['Norsk', 'Norvegese', 'no', 'no'],
            'fi'    => ['Suomi', 'Finlandese', 'fi', 'fi'],
            'tr'    => ['Türkçe', 'Turco', 'tr', 'tr'],
            'ar'    => ['العربية', 'Arabo', 'ar', 'ar'],
            'he'    => ['עברית', 'Ebraico', 'iw', 'he'],
            'zh-cn' => ['中文 (简体)', 'Cinese semplificato', 'zh-CN', 'zh-Hans'],
            'zh-tw' => ['中文 (繁體)', 'Cinese tradizionale', 'zh-TW', 'zh-Hant'],
            'ja'    => ['日本語', 'Giapponese', 'ja', 'ja'],
            'ko'    => ['한국어', 'Coreano', 'ko', 'ko'],
            'hi'    => ['हिन्दी', 'Hindi', 'hi', 'hi'],
            'sq'    => ['Shqip', 'Albanese', 'sq', 'sq'],
        ];
        return apply_filters('vml_languages', $list);
    }

    public static function exists($code)
    {
        return isset(self::all()[$code]);
    }

    public static function native_name($code)
    {
        $l = self::all();
        return isset($l[$code]) ? $l[$code][0] : strtoupper($code);
    }

    public static function italian_name($code)
    {
        $l = self::all();
        return isset($l[$code]) ? $l[$code][1] : strtoupper($code);
    }

    public static function google_code($code)
    {
        $l = self::all();
        return isset($l[$code]) ? $l[$code][2] : $code;
    }

    public static function hreflang($code)
    {
        $l = self::all();
        return isset($l[$code]) ? $l[$code][3] : $code;
    }

    /** Bandiera SVG inline (fallback: badge con il codice). */
    public static function flag($code)
    {
        static $n = 0;
        $n++;
        $v = function ($a, $b, $c) { // tricolore verticale
            return '<rect width="10" height="20" fill="' . $a . '"/><rect x="10" width="10" height="20" fill="' . $b . '"/><rect x="20" width="10" height="20" fill="' . $c . '"/>';
        };
        $h = function ($a, $b, $c) { // tricolore orizzontale
            return '<rect width="30" height="6.67" fill="' . $a . '"/><rect y="6.67" width="30" height="6.67" fill="' . $b . '"/><rect y="13.33" width="30" height="6.67" fill="' . $c . '"/>';
        };
        $flags = [
            'it' => $v('#009246', '#fff', '#ce2b37'),
            'fr' => $v('#0055a4', '#fff', '#ef4135'),
            'de' => $h('#000', '#dd0000', '#ffce00'),
            'nl' => $h('#ae1c28', '#fff', '#21468b'),
            'ru' => $h('#fff', '#0039a6', '#d52b1e'),
            'bg' => $h('#fff', '#00966e', '#d62612'),
            'hu' => $h('#ce2939', '#fff', '#477050'),
            'ro' => $v('#002b7f', '#fcd116', '#ce1126'),
            'es' => '<rect width="30" height="20" fill="#aa151b"/><rect y="5" width="30" height="10" fill="#f1bf00"/>',
            'pt' => '<rect width="30" height="20" fill="#da291c"/><rect width="12" height="20" fill="#046a38"/><circle cx="12" cy="10" r="3.5" fill="#ffe900"/>',
            'pl' => '<rect width="30" height="20" fill="#dc143c"/><rect width="30" height="10" fill="#fff"/>',
            'uk' => '<rect width="30" height="20" fill="#ffd500"/><rect width="30" height="10" fill="#005bbb"/>',
            'en' => '<clipPath id="vmlgb' . $n . '"><path d="M15,10h15v10zv10h-15zh-15v-10zv-10h15z"/></clipPath>'
                . '<rect width="30" height="20" fill="#012169"/>'
                . '<path d="M0,0L30,20M30,0L0,20" stroke="#fff" stroke-width="4"/>'
                . '<path d="M0,0L30,20M30,0L0,20" clip-path="url(#vmlgb' . $n . ')" stroke="#c8102e" stroke-width="2.4"/>'
                . '<path d="M15,0v20M0,10h30" stroke="#fff" stroke-width="6"/>'
                . '<path d="M15,0v20M0,10h30" stroke="#c8102e" stroke-width="3.6"/>',
        ];
        if (!isset($flags[$code])) {
            return '<span class="vml-flag vml-flag-code">' . esc_html(strtoupper(substr($code, 0, 2))) . '</span>';
        }
        return '<svg class="vml-flag" viewBox="0 0 30 20" width="21" height="14" aria-hidden="true" focusable="false">' . $flags[$code] . '</svg>';
    }
}
