<?php
/**
 * Plugin Name: Recrutement Tracker Pro
 * Description: Système de gestion de formulaires de recrutement avec tracking Meta Ads.
 * Version: 1.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RT_PATH', plugin_dir_path( __FILE__ ) );
define( 'RT_URL', plugin_dir_url( __FILE__ ) );

require_once RT_PATH . 'includes/class-cpt.php';
require_once RT_PATH . 'includes/class-admin-view.php';
require_once RT_PATH . 'includes/class-shortcode.php';
require_once RT_PATH . 'includes/class-submission.php';
require_once RT_PATH . 'includes/class-form-config.php';

function rt_create_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rt_visits';
    $charset_collate = $wpdb->get_charset_collate();

    // SQL corrigé : fbclid et visit_url passent en TEXT pour ne plus jamais bloquer
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        form_id int(11) NOT NULL,
        ip_address varchar(45) NOT NULL,
        utm_source varchar(255) DEFAULT '',
        fbclid text,
        visit_url text,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    update_option('rt_db_version', '1.2');
}
register_activation_hook( __FILE__, 'rt_create_db' );

function rt_init_plugin() {
    new RT_CPT();
    new RT_Form_Config();
    new RT_Shortcode();
    new RT_Submission();
    if ( is_admin() ) {
        new RT_Admin_View();
    }
    
    // Vérification de sécurité pour la table
    if ( get_option('rt_db_version') != '1.2' ) {
        rt_create_db();
    }
}
add_action( 'plugins_loaded', 'rt_init_plugin' );