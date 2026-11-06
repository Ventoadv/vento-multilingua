<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;
delete_option('vml_settings');
delete_transient('vml_slug_maps');
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'vml\\_strings\\_%'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_vml\\_%'");
$wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '\\_vml\\_%'");
