<?php
if (!defined('ABSPATH')) exit;

/**
 * Sitemap dinamica per una lingua aggiuntiva dentro le sitemap native di WordPress (wp-sitemap.xml).
 * File: /wp-sitemap-vml{lingua}-p-{post_type}-{n}.xml e /wp-sitemap-vml{lingua}-t-{tassonomia}-{n}.xml
 */
class VML_Sitemap_Provider extends WP_Sitemaps_Provider
{
    private $lang;

    public function __construct($lang)
    {
        $this->lang = $lang;
        $this->name = self::provider_name($lang);
        $this->object_type = 'vml';
    }

    /** Nome del provider (solo lettere minuscole, come richiesto dalle regole delle sitemap WP). */
    public static function provider_name($lang)
    {
        return 'vml' . preg_replace('/[^a-z]/', '', strtolower($lang));
    }

    public function get_object_subtypes()
    {
        $out = [];
        foreach (VML_Sitemaps::subtypes() as $s) $out[$s] = (object) ['name' => $s];
        return $out;
    }

    public function get_url_list($page_num, $object_subtype = '')
    {
        return VML_Sitemaps::url_list($this->lang, $object_subtype, $page_num, wp_sitemaps_get_max_urls($this->object_type));
    }

    public function get_max_num_pages($object_subtype = '')
    {
        return VML_Sitemaps::max_pages($object_subtype, wp_sitemaps_get_max_urls($this->object_type));
    }
}
