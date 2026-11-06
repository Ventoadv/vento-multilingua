<?php
if (!defined('ABSPATH')) exit;

/**
 * Box "Traduzioni: slug e SEO" su pagine/articoli e campi slug tradotti sui termini.
 */
class VML_Meta
{
    public static function init()
    {
        if (!is_admin()) return;
        add_action('add_meta_boxes', [__CLASS__, 'add_box']);
        add_action('save_post', [__CLASS__, 'save_post'], 10, 2);
        add_action('admin_init', [__CLASS__, 'term_hooks']);
    }

    public static function post_types()
    {
        $types = get_post_types(['public' => true], 'names');
        unset($types['attachment'], $types['elementor_library'], $types['e-landing-page'], $types['elementor-hf']);
        return apply_filters('vml_post_types', array_values($types));
    }

    public static function taxonomies()
    {
        $tax = get_taxonomies(['public' => true], 'names');
        unset($tax['post_format'], $tax['elementor_library_type'], $tax['elementor_library_category']);
        return apply_filters('vml_taxonomies', array_values($tax));
    }

    public static function add_box()
    {
        if (!VML_Settings::extra_languages()) return;
        foreach (self::post_types() as $pt) {
            add_meta_box('vml-meta', __('Traduzioni: slug e SEO', 'vento-multilingua'), [__CLASS__, 'render_box'], $pt, 'normal', 'default');
        }
    }

    public static function render_box($post)
    {
        wp_nonce_field('vml_meta_' . $post->ID, 'vml_meta_nonce');
        $orig_path = VML_Slugs::rel_path(get_permalink($post));
        echo '<div class="vml-meta-box" id="vml-meta">';
        echo '<p style="margin-top:0;color:#646970">' . esc_html__('Slug originale:', 'vento-multilingua') . ' <code>' . esc_html($post->post_name) . '</code>. ' . esc_html__('Lascia vuoto uno slug per usare quello originale. Titolo e descrizione SEO vengono serviti lato server e indicizzati.', 'vento-multilingua') . '</p>';
        echo '<table class="widefat striped" style="border:0"><thead><tr><th style="width:110px">' . esc_html__('Lingua', 'vento-multilingua') . '</th><th>' . esc_html__('Slug', 'vento-multilingua') . '</th><th>' . esc_html__('Titolo SEO', 'vento-multilingua') . '</th><th>' . esc_html__('Meta description', 'vento-multilingua') . '</th></tr></thead><tbody>';
        foreach (VML_Settings::extra_languages() as $l) {
            $slug = get_post_meta($post->ID, '_vml_slug_' . $l, true);
            $title = get_post_meta($post->ID, '_vml_title_' . $l, true);
            $desc = get_post_meta($post->ID, '_vml_desc_' . $l, true);
            $url = $post->post_status === 'publish' && $orig_path !== '' ? VML_Router::url_for($orig_path, $l) : '';
            echo '<tr><td><strong>' . esc_html(VML_Languages::native_name($l)) . '</strong>';
            if ($url) echo '<br><a href="' . esc_url($url) . '" target="_blank" style="font-size:11px">' . esc_html__('Apri', 'vento-multilingua') . ' ↗</a>';
            echo '</td>';
            echo '<td><input type="text" class="widefat" name="vml_slug[' . esc_attr($l) . ']" value="' . esc_attr(urldecode((string) $slug)) . '" placeholder="' . esc_attr(urldecode($post->post_name)) . '"></td>';
            echo '<td><input type="text" class="widefat" name="vml_title[' . esc_attr($l) . ']" value="' . esc_attr($title) . '"></td>';
            echo '<td><textarea class="widefat" rows="2" name="vml_desc[' . esc_attr($l) . ']">' . esc_textarea($desc) . '</textarea></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function save_post($post_id, $post)
    {
        if (!isset($_POST['vml_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vml_meta_nonce'])), 'vml_meta_' . $post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) return;

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput -- sanificati per campo sotto.
        $slugs = isset($_POST['vml_slug']) && is_array($_POST['vml_slug']) ? wp_unslash($_POST['vml_slug']) : [];
        $titles = isset($_POST['vml_title']) && is_array($_POST['vml_title']) ? wp_unslash($_POST['vml_title']) : [];
        $descs = isset($_POST['vml_desc']) && is_array($_POST['vml_desc']) ? wp_unslash($_POST['vml_desc']) : [];
        // phpcs:enable

        remove_action('save_post', [__CLASS__, 'save_post'], 10);
        foreach (VML_Settings::extra_languages() as $l) {
            if (array_key_exists($l, $slugs)) VML_Slugs::save_post_slug($post_id, $l, $slugs[$l]);
            foreach (['title' => $titles, 'desc' => $descs] as $what => $src) {
                if (!array_key_exists($l, $src)) continue;
                $val = $what === 'desc' ? sanitize_textarea_field($src[$l]) : sanitize_text_field($src[$l]);
                if ($val === '') delete_post_meta($post_id, '_vml_' . $what . '_' . $l);
                else update_post_meta($post_id, '_vml_' . $what . '_' . $l, $val);
            }
        }
        add_action('save_post', [__CLASS__, 'save_post'], 10, 2);
        VML_Slugs::flush();
    }

    /* ---------------- Termini ---------------- */

    public static function term_hooks()
    {
        foreach (self::taxonomies() as $tax) {
            add_action($tax . '_edit_form_fields', [__CLASS__, 'term_fields'], 10, 2);
            add_action('edited_' . $tax, [__CLASS__, 'save_term'], 10, 2);
        }
    }

    public static function term_fields($term, $taxonomy)
    {
        wp_nonce_field('vml_term_' . $term->term_id, 'vml_term_nonce');
        foreach (VML_Settings::extra_languages() as $l) {
            $slug = get_term_meta($term->term_id, '_vml_slug_' . $l, true);
            $title = get_term_meta($term->term_id, '_vml_title_' . $l, true);
            $desc = get_term_meta($term->term_id, '_vml_desc_' . $l, true);
            echo '<tr class="form-field"><th scope="row">' . esc_html(sprintf(__('Traduzione %s', 'vento-multilingua'), VML_Languages::native_name($l))) . '</th><td>';
            echo '<p><label>' . esc_html__('Slug', 'vento-multilingua') . '<br><input type="text" name="vml_slug[' . esc_attr($l) . ']" value="' . esc_attr(urldecode((string) $slug)) . '" placeholder="' . esc_attr(urldecode($term->slug)) . '"></label></p>';
            echo '<p><label>' . esc_html__('Titolo SEO', 'vento-multilingua') . '<br><input type="text" name="vml_title[' . esc_attr($l) . ']" value="' . esc_attr($title) . '"></label></p>';
            echo '<p><label>' . esc_html__('Meta description', 'vento-multilingua') . '<br><textarea rows="2" name="vml_desc[' . esc_attr($l) . ']">' . esc_textarea($desc) . '</textarea></label></p>';
            echo '</td></tr>';
        }
    }

    public static function save_term($term_id, $tt_id)
    {
        if (!isset($_POST['vml_term_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vml_term_nonce'])), 'vml_term_' . $term_id)) return;
        if (!current_user_can('manage_categories')) return;
        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) return;
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput
        $slugs = isset($_POST['vml_slug']) && is_array($_POST['vml_slug']) ? wp_unslash($_POST['vml_slug']) : [];
        $titles = isset($_POST['vml_title']) && is_array($_POST['vml_title']) ? wp_unslash($_POST['vml_title']) : [];
        $descs = isset($_POST['vml_desc']) && is_array($_POST['vml_desc']) ? wp_unslash($_POST['vml_desc']) : [];
        // phpcs:enable
        foreach (VML_Settings::extra_languages() as $l) {
            if (array_key_exists($l, $slugs)) VML_Slugs::save_term_slug($term_id, $term->taxonomy, $l, $slugs[$l]);
            foreach (['title' => $titles, 'desc' => $descs] as $what => $src) {
                if (!array_key_exists($l, $src)) continue;
                $val = $what === 'desc' ? sanitize_textarea_field($src[$l]) : sanitize_text_field($src[$l]);
                if ($val === '') delete_term_meta($term_id, '_vml_' . $what . '_' . $l);
                else update_term_meta($term_id, '_vml_' . $what . '_' . $l, $val);
            }
        }
        VML_Slugs::flush();
    }
}
