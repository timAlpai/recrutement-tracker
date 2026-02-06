<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RT_Admin_View {
    public function __construct() {
        // Colonnes pour les CANDIDATURES
        add_filter( 'manage_candidature_posts_columns', [ $this, 'add_candidature_columns' ] );
        add_action( 'manage_candidature_posts_custom_column', [ $this, 'fill_candidature_columns' ], 10, 2 );
        add_action( 'add_meta_boxes', [ $this, 'add_candidature_metaboxes' ] );

        // Colonnes pour les FORMULAIRES (Stats)
        add_filter( 'manage_rt_form_posts_columns', [ $this, 'add_form_stats_columns' ] );
        add_action( 'manage_rt_form_posts_custom_column', [ $this, 'fill_form_stats_columns' ], 10, 2 );
    }

    // --- PARTIE LISTE DES CANDIDATURES ---
    public function add_candidature_columns( $columns ) {
        $new_columns = [];
        foreach ( $columns as $key => $value ) {
            $new_columns[$key] = $value;
            if ( $key === 'title' ) {
                $new_columns['form_origin'] = 'Formulaire';
                $new_columns['utm_source']  = 'Source UTM';
                $new_columns['fb_status']   = 'Meta Tracking';
            }
        }
        return $new_columns;
    }

    public function fill_candidature_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'form_origin':
                $parent_id = get_post_meta( $post_id, '_form_parent_id', true );
                echo $parent_id ? get_the_title( $parent_id ) : '—';
                break;
            case 'utm_source':
                echo get_post_meta( $post_id, 'utm_source', true ) ?: 'Direct / Autre';
                break;
            case 'fb_status':
                echo get_post_meta( $post_id, 'fbclid', true ) ? '✅ Meta Ads' : '—';
                break;
        }
    }

    // --- PARTIE LISTE DES FORMULAIRES (STATS) ---
    public function add_form_stats_columns( $columns ) {
        $columns['shortcode'] = 'Shortcode';
        $columns['views'] = 'Vues (Visiteurs)';
        $columns['subs'] = 'Candidatures';
        $columns['rate'] = '% Conversion';
        return $columns;
    }

    public function fill_form_stats_columns( $column, $post_id ) {
        global $wpdb;
        $table_visits = $wpdb->prefix . 'rt_visits';

        switch ( $column ) {
            case 'shortcode':
                echo '<code>[rt_form id="' . $post_id . '"]</code>';
                break;

            case 'views':
                $views = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_visits WHERE form_id = %d", $post_id ) );
                echo "<strong>" . ($views ?: 0) . "</strong>";
                break;

            case 'subs':
                $subs = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_form_parent_id' AND meta_value = %d", $post_id ) );
                echo "<strong>" . ($subs ?: 0) . "</strong>";
                break;

            case 'rate':
                $views = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_visits WHERE form_id = %d", $post_id ) );
                $subs = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_form_parent_id' AND meta_value = %d", $post_id ) );
                
                if ( $views > 0 ) {
                    $perc = round( ($subs / $views) * 100, 1 );
                    $color = ($perc >= 10) ? '#46b450' : '#ffa500';
                    echo "<span style='color:$color; font-weight:bold;'>$perc %</span>";
                } else {
                    echo "<span style='color:#ccc;'>—</span>";
                }
                break;
        }
    }

    // --- BOITE DE DETAILS DANS L'EDITION ---
    public function add_candidature_metaboxes() {
        add_meta_box('rt_details_candidat', 'Détails de la Candidature', [$this, 'render_metabox'], 'candidature', 'normal', 'high');
    }

 public function render_metabox( $post ) {
        $all_meta = get_post_custom($post->ID);
        
        echo '<div style="padding:10px; font-size:14px; line-height:1.6;">';
        
        // On sépare le tracking des données du formulaire
        $tracking = [];
        $form_data = [];

        foreach ($all_meta as $key => $values) {
            $value = $values[0];
            if (empty($value) || $key[0] === '_') continue; // Ignore les metas privées de WP

            if (in_array($key, ['utm_source', 'utm_medium', 'utm_campaign', 'fbclid', 'visit_url'])) {
                $tracking[$key] = $value;
            } else {
                $form_data[$key] = $value;
            }
        }

        echo '<h4>📄 Données du Formulaire</h4>';
        foreach ($form_data as $key => $val) {
            $clean_label = str_replace(['custom_', 'user_', '_'], ['', '', ' '], $key);
            echo '<p><strong>' . ucfirst($clean_label) . ' :</strong> ' . esc_html($val) . '</p>';
        }

        echo '<hr><h4>🔗 Tracking Meta Ads</h4>';
        foreach ($tracking as $key => $val) {
            echo '<p><strong>' . strtoupper($key) . ' :</strong> <small>' . esc_html($val) . '</small></p>';
        }

        echo '<p><strong>🌐 IP :</strong> ' . get_post_meta($post->ID, '_ip_address', true) . '</p>';
        echo '</div>';
    }
	
	public function render_traffic_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rt_visits';
        
        // Récupérer les 50 dernières visites
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT 500");

        echo '<div class="wrap"><h1>Journal du Trafic (Double Vue Meta Ads)</h1>';
        echo '<p>Voici les dernières visites détectées sur vos formulaires. Utile pour vérifier les clics envoyés par Meta.</p>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>
                <th style="width:150px;">Date/Heure</th>
                <th>Formulaire</th>
                <th>URL de la Page</th>
                <th>Source UTM</th>
                <th>Facebook Click ID (fbclid)</th>
                <th>IP</th>
              </tr></thead>';
        echo '<tbody>';

        if ($logs) {
            foreach ($logs as $log) {
                $form_name = get_the_title($log->form_id);
                $fb_status = !empty($log->fbclid) ? '<code style="color:green; word-break:break-all;">' . esc_html($log->fbclid) . '</code>' : '<span style="color:#ccc;">Aucun</span>';
                
                echo '<tr>';
                echo '<td>' . esc_html($log->time) . '</td>';
                echo '<td>' . esc_html($form_name) . '</td>';
                echo '<td><small>' . esc_url($log->visit_url) . '</small></td>';
                echo '<td>' . esc_html($log->utm_source) . '</td>';
                echo '<td>' . $fb_status . '</td>';
                echo '<td>' . esc_html($log->ip_address) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6">Aucune visite enregistrée pour le moment.</td></tr>';
        }

        echo '</tbody></table></div>';
    }
}