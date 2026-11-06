<?php
if (!defined('ABSPATH')) exit;

/**
 * Editor visuale nel frontend: clic sul testo -> correzione salvata nel dizionario della lingua.
 */
class VML_Editor
{
    public static function init()
    {
        add_action('admin_bar_menu', [__CLASS__, 'admin_bar'], 100);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 20);
        add_action('wp_ajax_vml_save_string', [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_vml_delete_string', [__CLASS__, 'ajax_delete']);
    }

    public static function admin_bar($bar)
    {
        if (is_admin() || !is_admin_bar_showing() || !current_user_can(VML_Settings::capability()) || VML_Frontend::is_excluded()) return;
        $orig = VML_Router::current_original();
        $current = VML_Router::lang();
        $edit = VML_Frontend::edit_mode();

        $title = $edit
            ? '✏️ ' . sprintf(esc_html__('Modalità traduzione: %s (esci)', 'vento-multilingua'), esc_html(VML_Languages::native_name($current)))
            : '🌐 ' . esc_html__('Traduzioni', 'vento-multilingua');
        $href = $edit ? remove_query_arg('vml_edit', VML_Router::url_for($orig, $current)) : '#';

        $bar->add_node(['id' => 'vml', 'title' => $title, 'href' => $href]);
        foreach (VML_Settings::extra_languages() as $l) {
            $bar->add_node([
                'parent' => 'vml',
                'id'     => 'vml-edit-' . $l,
                'title'  => sprintf(esc_html__('Modalità traduzione: %s', 'vento-multilingua'), esc_html(VML_Languages::native_name($l))),
                'href'   => add_query_arg('vml_edit', '1', VML_Router::url_for($orig, $l)),
            ]);
        }
        $obj = get_queried_object();
        if ($obj instanceof WP_Post) {
            $bar->add_node(['parent' => 'vml', 'id' => 'vml-seo', 'title' => esc_html__('Slug e SEO di questa pagina', 'vento-multilingua'), 'href' => get_edit_post_link($obj->ID) . '#vml-meta']);
        } elseif ($obj instanceof WP_Term) {
            $bar->add_node(['parent' => 'vml', 'id' => 'vml-seo', 'title' => esc_html__('Slug di questo archivio', 'vento-multilingua'), 'href' => get_edit_term_link($obj->term_id, $obj->taxonomy)]);
        }
        $bar->add_node(['parent' => 'vml', 'id' => 'vml-strings', 'title' => esc_html__('Elenco traduzioni', 'vento-multilingua'), 'href' => admin_url('admin.php?page=vml-strings&lang=' . (VML_Router::is_default() ? (VML_Settings::extra_languages()[0] ?? '') : $current))]);
    }

    public static function enqueue()
    {
        if (!VML_Frontend::edit_mode() || VML_Frontend::is_excluded()) return;
        $lang = VML_Router::lang();
        wp_enqueue_style('vml-editor', VML_URL . 'assets/css/vml-editor.css', [], VML_VERSION);
        wp_enqueue_script('vml-editor', VML_URL . 'assets/js/vml-editor.js', [], VML_VERSION, true);
        wp_localize_script('vml-editor', 'vmlEditor', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('vml_editor'),
            'lang'     => $lang,
            'langName' => VML_Languages::native_name($lang),
            'exitUrl'  => remove_query_arg('vml_edit', VML_Router::url_for(VML_Router::current_original(), $lang)),
            'strings'  => (object) VML_Strings::get($lang),
            'i18n'     => [
                'bar'         => __('Modalità traduzione', 'vento-multilingua'),
                'hint'        => __('Clicca un testo (o selezionane una parte) per tradurlo. La traduzione automatica è sospesa mentre modifichi.', 'vento-multilingua'),
                'exit'        => __('Esci', 'vento-multilingua'),
                'original'    => __('Testo originale', 'vento-multilingua'),
                'translation' => __('Traduzione', 'vento-multilingua'),
                'save'        => __('Salva', 'vento-multilingua'),
                'delete'      => __('Elimina', 'vento-multilingua'),
                'cancel'      => __('Annulla', 'vento-multilingua'),
                'saving'      => __('Salvataggio…', 'vento-multilingua'),
                'error'       => __('Errore durante il salvataggio', 'vento-multilingua'),
                'empty'       => __('Nessun testo trovato in questo punto.', 'vento-multilingua'),
            ],
        ]);
    }

    private static function check()
    {
        if (!current_user_can(VML_Settings::capability())) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }
        check_ajax_referer('vml_editor', 'nonce');
        $lang = isset($_POST['lang']) ? sanitize_key(wp_unslash($_POST['lang'])) : '';
        if (!VML_Settings::is_extra($lang)) {
            wp_send_json_error(['message' => 'Lingua non valida'], 400);
        }
        return $lang;
    }

    public static function ajax_save()
    {
        $lang = self::check();
        $original = isset($_POST['original']) ? sanitize_text_field(wp_unslash($_POST['original'])) : '';
        $translation = isset($_POST['translation']) ? sanitize_text_field(wp_unslash($_POST['translation'])) : '';
        if ($original === '' || $translation === '') {
            wp_send_json_error(['message' => 'Dati incompleti'], 400);
        }
        if (!VML_Strings::set($lang, $original, $translation)) {
            wp_send_json_error(['message' => 'Testo non valido o troppo lungo'], 400);
        }
        wp_send_json_success(['original' => VML_Strings::normalize($original), 'translation' => VML_Strings::normalize($translation)]);
    }

    public static function ajax_delete()
    {
        $lang = self::check();
        $original = isset($_POST['original']) ? sanitize_text_field(wp_unslash($_POST['original'])) : '';
        VML_Strings::delete($lang, $original);
        wp_send_json_success();
    }
}
