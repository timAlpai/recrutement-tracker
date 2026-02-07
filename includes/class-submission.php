<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RT_Submission {
    public function __construct() {
        add_action( 'admin_post_submit_recrutement_form', [ $this, 'handle_form_submission' ] );
        add_action( 'admin_post_nopriv_submit_recrutement_form', [ $this, 'handle_form_submission' ] );
    }

    public function handle_form_submission() {
        if ( ! isset( $_POST['rt_nonce'] ) || ! wp_verify_nonce( $_POST['rt_nonce'], 'rt_submit_action' ) ) {
            wp_die( 'Erreur de sécurité' );
        }

        $parent_id = intval( $_POST['form_parent_id'] );
        $fname = sanitize_text_field($_POST['first_name'] ?? '');
        $lname = sanitize_text_field($_POST['last_name'] ?? '');

        $title = trim($fname . ' ' . $lname);
        if(empty($title)) $title = 'Entrepreneur Anonyme';

        $post_id = wp_insert_post([
            'post_title'  => $title . ' [' . date('d/m/Y H:i') . ']',
            'post_type'   => 'candidature',
            'post_status' => 'publish',
        ]);

        if ( $post_id ) {
            // Sauvegarde dynamique de TOUS les champs envoyés (standards et custom)
            foreach ( $_POST as $key => $value ) {
                // On enregistre si le champ commence par nos préfixes ou est dans notre liste
                if ( strpos($key, 'first_') === 0 || strpos($key, 'last_') === 0 || 
                     strpos($key, 'user_') === 0 || strpos($key, 'custom_') === 0 ||
                     in_array($key, ['company', 'subject', 'message', 'utm_source', 'fbclid']) ) {
                    
                    update_post_meta( $post_id, sanitize_key($key), sanitize_text_field($value) );
                }
            }
            
            update_post_meta( $post_id, '_form_parent_id', $parent_id );
            update_post_meta( $post_id, '_ip_address', $_SERVER['REMOTE_ADDR'] );
            update_post_meta( $post_id, '_user_agent', $_SERVER['HTTP_USER_AGENT'] );

            $custom_redirect = get_post_meta( $parent_id, '_rt_redirect_url', true );
            if ( ! empty( $custom_redirect ) ) {
                wp_redirect( $custom_redirect );
            } else {
                $redirect_url = add_query_arg( 'status', 'success', $_SERVER['HTTP_REFERER'] );
                wp_redirect( $redirect_url );
            }
            exit;
        }
    }
}