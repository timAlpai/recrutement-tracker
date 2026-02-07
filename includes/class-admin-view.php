<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RT_Admin_View {
    public function __construct() {
        // Colonnes pour les CANDIDATURES
        add_filter( 'manage_candidature_posts_columns', [ $this, 'add_candidature_columns' ] );
        add_action( 'manage_candidature_posts_custom_column', [ $this, 'fill_candidature_columns' ], 10, 2 );
        add_action( 'add_meta_boxes', [ $this, 'add_candidature_metaboxes' ] );
        
        // Export CSV
        add_action( 'admin_init', [ $this, 'handle_csv_export' ] );
        add_action( 'manage_posts_extra_tablenav', [ $this, 'add_export_button' ] );

        // Colonnes pour les FORMULAIRES (Stats)
        add_filter( 'manage_rt_form_posts_columns', [ $this, 'add_form_stats_columns' ] );
        add_action( 'manage_rt_form_posts_custom_column', [ $this, 'fill_form_stats_columns' ], 10, 2 );
    }

    // --- BOUTON EXPORT CSV ---
    public function add_export_button( $which ) {
        global $typenow;
        if ( 'candidature' !== $typenow || 'top' !== $which ) return;
        echo '<div class="alignleft actions"><a href="' . add_query_arg('rt_export', 'csv') . '" class="button button-primary">Exporter en CSV</a></div>';
    }

   public function handle_csv_export() {
        if ( isset($_GET['rt_export']) && $_GET['rt_export'] === 'csv' ) {
            // 1. Vérification des droits
            if ( !current_user_can('manage_options') ) return;

            // 2. Nettoyer tout tampon de sortie pour éviter des lignes vides ou du HTML parasite
            if (ob_get_length()) ob_end_clean();

            // 3. En-têtes pour le téléchargement
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=leads-recrutement-' . date('Y-m-d') . '.csv');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');
            
            // Ajouter le BOM UTF-8 pour qu'Excel reconnaisse les accents immédiatement
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // 4. Définir les colonnes du CSV
            fputcsv($output, ['Date', 'Formulaire', 'Nom Complet', 'Email', 'Telephone', 'Source UTM', 'URL Page', 'FBCLID']);

            // 5. RÉCUPÉRATION DES DONNÉES
            // On force 'post_status' => 'any' pour inclure les Brouillons et les Publiés
            $args = [
                'post_type'      => 'candidature',
                'posts_per_page' => -1,
                'post_status'    => 'any', // <--- IMPORTANT : récupère tout
                'orderby'        => 'date',
                'order'          => 'DESC'
            ];
            $candidates = get_posts($args);

            if ( !empty($candidates) ) {
                foreach ($candidates as $c) {
                    $parent_id = get_post_meta($c->ID, '_form_parent_id', true);
                    
                    // On récupère chaque meta
                    $email  = get_post_meta($c->ID, 'user_email', true);
                    $phone  = get_post_meta($c->ID, 'user_phone', true);
                    $source = get_post_meta($c->ID, 'utm_source', true);
                    $url    = get_post_meta($c->ID, 'visit_url', true);
                    $fbclid = get_post_meta($c->ID, 'fbclid', true);

                    fputcsv($output, [
                        $c->post_date,
                        get_the_title($parent_id) ?: 'N/A',
                        $c->post_title,
                        $email,
                        $phone,
                        $source,
                        $url,
                        $fbclid
                    ]);
                }
            }

            fclose($output);
            exit;
        }
    }

    // --- RESTE DU CODE (SANS CHANGEMENT) ---
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
                echo $parent_id ? get_the_title( $parent_id ) : '—'; break;
            case 'utm_source':
                echo get_post_meta( $post_id, 'utm_source', true ) ?: 'Direct / Autre'; break;
            case 'fb_status':
                echo get_post_meta( $post_id, 'fbclid', true ) ? '✅ Meta Ads' : '—'; break;
        }
    }

    public function add_form_stats_columns( $columns ) {
        $columns['shortcode'] = 'Shortcode';
        $columns['views'] = 'Vues';
        $columns['subs'] = 'Candidatures';
        $columns['rate'] = '% Conv';
        return $columns;
    }

    public function fill_form_stats_columns( $column, $post_id ) {
        global $wpdb;
        $table_visits = $wpdb->prefix . 'rt_visits';
        switch ( $column ) {
            case 'shortcode': echo '<code>[rt_form id="' . $post_id . '"]</code>'; break;
            case 'views':
                $views = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_visits WHERE form_id = %d", $post_id ) );
                echo "<strong>" . ($views ?: 0) . "</strong>"; break;
            case 'subs':
                $subs = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_form_parent_id' AND meta_value = %d", $post_id ) );
                echo "<strong>" . ($subs ?: 0) . "</strong>"; break;
            case 'rate':
                $views = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_visits WHERE form_id = %d", $post_id ) );
                $subs = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_form_parent_id' AND meta_value = %d", $post_id ) );
                if ( $views > 0 ) {
                    $perc = round( ($subs / $views) * 100, 1 );
                    echo "<span style='color:#46b450; font-weight:bold;'>$perc %</span>";
                } else { echo "—"; } break;
        }
    }

    public function render_traffic_page() {
        global $wpdb; $table_name = $wpdb->prefix . 'rt_visits';
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $an = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE utm_source = 'an'");
        $fb = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE fbclid != '' AND utm_source != 'an'");
        $direct = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE fbclid = '' AND utm_source != 'an'");

        echo '<div class="wrap"><h1>Journal du Trafic (Analyse Meta Ads)</h1>';
        echo '<div style="display:flex; gap:15px; margin:20px 0;">';
            $this->draw_stat_box('Total Visites', $total, '#23282d');
            $this->draw_stat_box('Audience Network (an)', $an, '#ed1c24', 'Trafic souvent accidentel');
            $this->draw_stat_box('Facebook Feed', $fb, '#3b5998', 'Trafic haute qualité');
            $this->draw_stat_box('Direct / Autre', $direct, '#666');
        echo '</div>';

        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT 500");
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th style="width:150px;">Date</th><th>Formulaire</th><th>Page</th><th>Source</th><th>FBCLID</th><th>IP</th></tr></thead><tbody>';
        if ($logs) {
            foreach ($logs as $log) {
                $form_name = $log->form_id ? get_the_title($log->form_id) : '<span style="color:#999;">Visite Simple</span>';
                echo "<tr><td><small>{$log->time}</small></td><td>{$form_name}</td><td><small style='color:#666;'>{$log->visit_url}</small></td><td><strong>{$log->utm_source}</strong></td><td><small style='word-break:break-all; font-size:10px;'>{$log->fbclid}</small></td><td>{$log->ip_address}</td></tr>";
            }
        }
        echo '</tbody></table></div>';
    }

    private function draw_stat_box($label, $count, $color, $note = '') {
        echo '<div style="background:#fff; padding:15px; border-radius:5px; border-left:5px solid '.$color.'; box-shadow:0 1px 2px rgba(0,0,0,0.1); flex:1;">';
        echo '<div style="font-size:11px; color:#999; text-transform:uppercase;">'.esc_html($label).'</div>';
        echo '<div style="font-size:22px; font-weight:bold; color:'.$color.';">'.number_format($count, 0, ',', ' ').'</div>';
        if($note) echo '<div style="font-size:9px; color:#aaa; margin-top:5px;">'.esc_html($note).'</div>';
        echo '</div>';
    }

    public function add_candidature_metaboxes() {
        add_meta_box('rt_details_candidat', 'Détails de la Candidature', [$this, 'render_metabox'], 'candidature', 'normal', 'high');
    }

    public function render_metabox( $post ) {
        $all_meta = get_post_custom($post->ID);
        echo '<div style="padding:10px; font-size:14px; line-height:1.6;">';
        echo '<h4>📄 Données du Formulaire</h4><table class="widefat striped">';
        foreach ($all_meta as $key => $values) {
            if ( strpos($key, '_') === 0 || in_array($key, ['utm_source', 'fbclid', 'visit_url', 'utm_medium', 'utm_campaign']) ) continue;
            $display_label = str_replace(['custom_', 'user_'], ['', ''], $key);
            echo '<tr><td style="font-weight:bold; width:200px;">' . ucwords($display_label) . '</td><td>' . esc_html($values[0]) . '</td></tr>';
        }
        echo '</table>';
        echo '<hr><h4>🔗 Tracking Meta</h4>';
        echo '<p><strong>Source :</strong> ' . get_post_meta($post->ID, 'utm_source', true) . '</p>';
        echo '<p><strong>FBCLID :</strong> <small style="word-break:break-all;">' . get_post_meta($post->ID, 'fbclid', true) . '</small></p>';
        echo '<p><strong>URL d\'arrivée :</strong> <small>' . get_post_meta($post->ID, 'visit_url', true) . '</small></p>';
        echo '</div>';
    }
}