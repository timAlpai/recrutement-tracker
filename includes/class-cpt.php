<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RT_CPT {
    public function __construct() {
        // 1. Créer le menu parent
        add_action( 'admin_menu', [ $this, 'register_parent_menu' ] );
        // 2. Créer les CPT
        add_action( 'init', [ $this, 'register_entities' ] );
    }

public function register_parent_menu() {
        add_menu_page(
            'Recrutement Tracker',
            'Recrutement',
            'manage_options',
            'rt_main_menu',
            '',
            'dashicons-id-alt',
            30
        );

        // On ajoute ce sous-menu manuellement
        add_submenu_page(
            'rt_main_menu',
            'Journal du Trafic',
            'Journal du Trafic',
            'manage_options',
            'rt_traffic_logs',
            [ $this, 'render_traffic_logs' ] // La fonction qui affichera le tableau
        );
    }

    // Fonction pour afficher la page des logs (on va appeler une méthode de Admin_View pour rester propre)
    public function render_traffic_logs() {
        $view = new RT_Admin_View();
        $view->render_traffic_page();
    }

    public function register_entities() {
        // --- 1. SOUS-MENU : CANDIDATURES ---
        register_post_type( 'candidature', [
            'labels' => [
                'name'               => 'Candidatures',
                'singular_name'      => 'Candidature',
                'all_items'          => 'Toutes les candidatures',
                'menu_name'          => 'Candidatures',
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => 'rt_main_menu', // Liaison au menu parent
            'supports'          => [ 'title', 'custom-fields' ],
            'capabilities'      => [ 'create_posts' => false ], // On empêche la création manuelle
            'map_meta_cap'      => true,
        ]);

        // --- 2. SOUS-MENU : CONFIG FORMULAIRES ---
        register_post_type( 'rt_form', [
            'labels' => [
                'name'               => 'Mes Formulaires',
                'singular_name'      => 'Formulaire',
                'add_new'            => 'Créer un formulaire',
                'all_items'          => 'Mes Formulaires',
                'menu_name'          => 'Configuration',
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => 'rt_main_menu', // Liaison au menu parent
            'supports'          => [ 'title' ],
            'has_archive'       => false,
        ]);

        // Note : On supprime le premier sous-menu automatique qui porte le nom du parent
        remove_submenu_page('rt_main_menu', 'rt_main_menu');
    }
}
