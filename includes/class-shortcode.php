<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RT_Shortcode {
    public function __construct() {
        add_shortcode( 'rt_form', [ $this, 'render_form' ] );
    }

    public function render_form( $atts ) {
        $atts = shortcode_atts( [ 'id' => null ], $atts );
        if ( ! $atts['id'] ) return "";

        global $wpdb;
        $table_name = $wpdb->prefix . 'rt_visits';

        // --- LOG DE LA VISITE ---
        $protocol = is_ssl() ? 'https://' : 'http://';
        $current_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        
        $source = 'Direct';
        if ( !empty($_GET['utm_source']) ) $source = $_GET['utm_source'];
        elseif ( !empty($_GET['fbclid']) ) $source = 'Facebook (Auto)';

        $wpdb->insert(
            $table_name,
            [
                'form_id'    => (int)$atts['id'],
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'utm_source' => sanitize_text_field($source),
                'fbclid'     => sanitize_textarea_field($_GET['fbclid'] ?? ''), // textarea pour accepter le long texte
                'visit_url'  => esc_url_raw($current_url) 
            ]
        );

        // --- RÉCUPÉRATION CONFIG ---
        $fields = get_post_meta( $atts['id'], '_rt_fields', true ) ?: [];
        $custom_labels = get_post_meta( $atts['id'], '_rt_labels', true ) ?: [];
        $btn_text = get_post_meta( $atts['id'], '_rt_btn_text', true ) ?: 'Soumettre';
        $get_label = function($id, $default) use ($custom_labels) {
            return !empty($custom_labels[$id]) ? $custom_labels[$id] : $default;
        };


        if ( isset($_GET['status']) && $_GET['status'] === 'success' ) {
            return '<div style="padding:20px; background:#e7f4e9; color:#1e4620; border:1px solid #c3e6cb; border-radius:8px;">✅ Merci ! Votre candidature a bien été enregistrée.</div>';
        }

        ob_start(); ?>
        <style>
            .rt-form-container { font-family: sans-serif; color: #333; max-width: 100%; }
            .rt-row { display: flex; gap: 20px; margin-bottom: 15px; }
            .rt-col { flex: 1; }
            .rt-group { margin-bottom: 20px; }
            .rt-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; }
            .rt-group label span { color: #ff4d4d; margin-left: 4px; }
            .rt-input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
            .rt-textarea { width: 100%; height: 120px; resize: vertical; }
            .rt-submit { background: #4da3ff; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 16px; cursor: pointer; font-weight: bold; }
            @media (max-width: 600px) { .rt-row { flex-direction: column; gap: 0; } }
        </style>

        <div class="rt-form-container">
            <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
                <?php wp_nonce_field( 'rt_submit_action', 'rt_nonce' ); ?>
                <input type="hidden" name="form_parent_id" value="<?php echo esc_attr($atts['id']); ?>">
                <input type="hidden" name="action" value="submit_recrutement_form">

                <div class="rt-row">
                    <?php if (in_array('first_name', $fields)): ?>
                    <div class="rt-col rt-group">
                        <label><?php echo $get_label('first_name', 'Prénom'); ?> <span>*</span></label>
                        <input type="text" name="first_name" class="rt-input" placeholder="Prénom" required>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('last_name', $fields)): ?>
                    <div class="rt-col rt-group">
                        <label><?php echo $get_label('last_name', 'Nom de famille'); ?> <span>*</span></label>
                        <input type="text" name="last_name" class="rt-input" placeholder="Nom de famille" required>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (in_array('user_email', $fields)): ?>
                <div class="rt-group">
                    <label><?php echo $get_label('user_email', 'E-mail'); ?> <span>*</span></label>
                    <input type="email" name="user_email" class="rt-input" placeholder="Adresse e-mail" required>
                </div>
                <?php endif; ?>

                <?php if (in_array('subject', $fields)): ?>
                <div class="rt-group">
                    <label><?php echo $get_label('subject', 'Sujet'); ?></label>
                    <input type="text" name="subject" class="rt-input" placeholder="Sujet">
                </div>
                <?php endif; ?>

                <?php if (in_array('message', $fields)): ?>
                <div class="rt-group">
                    <label><?php echo $get_label('message', 'Votre message'); ?> <span>*</span></label>
                    <textarea name="message" class="rt-input rt-textarea" placeholder="Votre message" required></textarea>
                </div>
                <?php endif; ?>

                 <?php
                $custom_fields_list = get_post_meta( $atts['id'], '_rt_custom_fields_list', true );
    if ( !empty($custom_fields_list) ) {
        $extra_fields = explode("\n", str_replace("\r", "", $custom_fields_list));
        foreach ( $extra_fields as $field_label ) {
            $field_label = trim($field_label);
            if ( empty($field_label) ) continue;
            $field_name = 'custom_' . sanitize_title($field_label);
            ?>
            <div class="rt-group">
                <label><?php echo esc_html($field_label); ?></label>
                <input type="text" name="<?php echo esc_attr($field_name); ?>" class="rt-input" placeholder="<?php echo esc_attr($field_label); ?>">
            </div>
            <?php
        }
    }
                <input type="hidden" name="utm_source" value="<?php echo esc_attr($_GET['utm_source'] ?? ''); ?>">
                <input type="hidden" name="fbclid" value="<?php echo esc_attr($_GET['fbclid'] ?? ''); ?>">

                <button type="submit" class="rt-submit"><?php echo esc_html($btn_text); ?></button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}