<?php
/**
 * Plugin Name: Vento Multilingua
 * Description: Sito multilingua con URL per lingua (/en/, /de/, /fr/), slug tradotti, traduzione automatica Google nel browser e correzioni manuali servite lato server (indicizzabili).
 * Version: 1.3.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Vento Adv
 * License: GPLv2 or later
 * Text Domain: vento-multilingua
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VML_VERSION', '1.3.2');
define('VML_FILE', __FILE__);
define('VML_PATH', plugin_dir_path(__FILE__));
define('VML_URL', plugin_dir_url(__FILE__));

require_once VML_PATH . 'includes/class-vml-languages.php';
require_once VML_PATH . 'includes/class-vml-settings.php';
require_once VML_PATH . 'includes/class-vml-strings.php';
require_once VML_PATH . 'includes/class-vml-slugs.php';
require_once VML_PATH . 'includes/class-vml-router.php';
require_once VML_PATH . 'includes/class-vml-frontend.php';
require_once VML_PATH . 'includes/class-vml-editor.php';
require_once VML_PATH . 'includes/class-vml-admin.php';
require_once VML_PATH . 'includes/class-vml-meta.php';
require_once VML_PATH . 'includes/class-vml-sitemaps.php';

register_activation_hook(__FILE__, ['VML_Settings', 'activate']);

add_action('plugins_loaded', function () {
    VML_Router::init();
    VML_Slugs::init();
    VML_Frontend::init();
    VML_Editor::init();
    VML_Meta::init();
    VML_Sitemaps::init();
    if (is_admin()) {
        VML_Admin::init();
    }
}, 1);

// Sitemap dinamiche per lingua, integrate in /wp-sitemap.xml (generate a ogni richiesta).
add_action('wp_sitemaps_init', function ($wp_sitemaps) {
    if (!apply_filters('vml_sitemaps_enabled', true)) return;
    require_once VML_PATH . 'includes/class-vml-sitemap-provider.php';
    foreach (VML_Settings::extra_languages() as $lang) {
        $wp_sitemaps->registry->add_provider(VML_Sitemap_Provider::provider_name($lang), new VML_Sitemap_Provider($lang));
    }
});

/* ------------------------------------------------------------------
 * API pubblica per temi e template
 * ------------------------------------------------------------------ */

/** Codice della lingua corrente (es. "it", "en"). */
function vml_current_lang()
{
    return VML_Router::lang();
}

/** URL della pagina corrente nella lingua indicata. */
function vml_url($lang)
{
    return VML_Router::url_for(VML_Router::current_original(), $lang);
}

/** Restituisce l'HTML del selettore lingue. */
function vml_switcher($args = [])
{
    return VML_Frontend::render_switcher($args);
}
