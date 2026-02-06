<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RT_Form_Config {
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_form_settings_box' ] );
        add_action( 'save_post', [ $this, 'save_form_settings' ] );
        
        // Ajoute le shortcode dans la liste des formulaires pour plus de facilité
        add_filter( 'manage_rt_form_posts_columns', [ $this, 'add_shortcode_column' ] );
        add_action( 'manage_rt_form_posts_custom_column', [ $this, 'fill_shortcode_column' ], 10, 2 );
    }

    public function add_form_settings_box() {
        add_meta_box( 
            'rt_form_fields', 
            'Configuration du Formulaire', 
            [ $this, 'render_box' ], 
            'rt_form', 
            'normal', 
            'high' 
        );
    }

    public function render_box( $post ) {
        $selected_fields = get_post_meta( $post->ID, '_rt_fields', true ) ?: [];
        $labels = get_post_meta( $post->ID, '_rt_labels', true ) ?: [];
        $custom_fields_raw = get_post_meta( $post->ID, '_rt_custom_fields_list', true ) ?: '';

        // Champs standards (avec Téléphone ajouté)
        $fields_def = [
            'first_name' => 'Prénom',
            'last_name'  => 'Nom de famille',
            'user_email' => 'E-mail',
            'user_phone' => 'Téléphone', // Ajouté
            'company'    => 'Nom de la Société', // Ajouté pour tes entrepreneurs
        ];

        echo '<h4>Champs Standards</h4>';
        echo '<table class="widefat striped"><thead><tr><th>Actif</th><th>Champ</th><th>Label personnalisé</th></tr></thead><tbody>';
        foreach ( $fields_def as $id => $default_label ) {
            $checked = in_array( $id, $selected_fields ) ? 'checked' : '';
            $val = isset($labels[$id]) ? esc_attr($labels[$id]) : '';
            echo "<tr>
                <td><input type='checkbox' name='rt_fields[]' value='$id' $checked></td>
                <td>$default_label</td>
                <td><input type='text' name='rt_labels[$id]' value='$val' placeholder='$default_label' style='width:100%'></td>
            </tr>";
        }
        echo '</tbody></table>';

        echo '<h4>Champs Dynamiques Additionnels</h4>';
        echo '<p>Ajoutez un champ par ligne (ex: Chiffre Affaires). Ils apparaîtront comme des champs texte.</p>';
        echo '<textarea name="rt_custom_fields_list" style="width:100%; height:100px;" placeholder="Ex: Nom de la société
Nombre d\'employés
Budget recrutement">' . esc_textarea($custom_fields_raw) . '</textarea>';

        echo '<p style="margin-top:20px;"><strong>Texte du bouton de validation :</strong><br>';
        echo '<input type="text" name="rt_btn_text" value="' . esc_attr($btn_text) . '" style="width:100%; padding:8px; margin-top:5px;"></p>';
        // Dans render_box, ajoute ce champ après le texte du bouton :
$redirect_url = get_post_meta( $post->ID, '_rt_redirect_url', true ) ?: '';

echo '<p style="margin-top:20px;"><strong>URL de redirection après succès (Optionnel) :</strong><br>';
echo '<input type="url" name="rt_redirect_url" value="' . esc_attr($redirect_url) . '" placeholder="https://tonsite.com/merci" style="width:100%; padding:8px; margin-top:5px;">';
echo '<br><small>Si vide, l\'utilisateur restera sur la page et verra le message de succès.</small></p>';
        echo '<div style="background: #e7f4e9; padding: 10px; border-radius: 5px; margin-top: 20px;">';
        echo '<strong>Shortcode à copier :</strong> <code>[rt_form id="' . $post->ID . '"]</code>';
        echo '</div>';
    }

    public function save_form_settings( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( isset( $_POST['rt_fields'] ) ) update_post_meta( $post_id, '_rt_fields', $_POST['rt_fields'] );
        if ( isset( $_POST['rt_labels'] ) ) update_post_meta( $post_id, '_rt_labels', $_POST['rt_labels'] );
        if ( isset( $_POST['rt_btn_text'] ) ) update_post_meta( $post_id, '_rt_btn_text', sanitize_text_field( $_POST['rt_btn_text'] ) );
        if ( isset( $_POST['rt_redirect_url'] ) ) {
    update_post_meta( $post_id, '_rt_redirect_url', esc_url_raw( $_POST['rt_redirect_url'] ) );
}
    if ( isset( $_POST['rt_custom_fields_list'] ) ) {
        update_post_meta( $post_id, '_rt_custom_fields_list', sanitize_textarea_field($_POST['rt_custom_fields_list']) );

	}

    
    }

    // Colonnes pour la liste des formulaires
    public function add_shortcode_column( $columns ) {
        $columns['shortcode'] = 'Shortcode';
        return $columns;
    }

    public function fill_shortcode_column( $column, $post_id ) {
        if ( $column === 'shortcode' ) {
            echo '<code>[rt_form id="' . $post_id . '"]</code>';
        }
    }
}