<?php

namespace Rurls\Admin;

class Settings {
  // local copy of the settings provided by the base plugin
  private $settings = array();

  // The controlled list of settings & associated
  // defined during construction for i18n reasons
  private $settings_fields = array();

  // options page slug
  private $options_page_name = 'rurls-settings';

  // options page settings group name
  private $settings_field_group;

  /**
   * Prepare the object and hook into WordPress
   * 
   * @param $settings
   */
  function __construct( $settings ) {
    $this->settings = $settings;
    $this->settings_field_group = RURLS_SETTINGS_NAME . '-group';
    $this->settings_fields = $this->get_settings_fields();

    // add our options page the the admin menu
    add_action( 'admin_menu', array( $this, 'admin_menu' ) );

    // register our settings
    add_action( 'admin_init', array( $this, 'admin_init' ) );
  }

  /**
   * Gather all settings fields and do some simple preprocessing
   * 
   * @return array|mixed|void
   */
  function get_settings_fields(){
    // Fields
    $image_sizes = get_intermediate_image_sizes();
    $image_sizes[] = 'full';

    $post_types = get_post_types( array( 'public' => true ) );
    unset( $post_types['attachment'] );

    $mime_types = array();
    foreach ( rurls_get_mime_types() as $type => $details ){
      $mime_types[ $type ] = $details['title'] . " <small><em>($type)</em></small>";
    }

    $fields = array(
      'mime_types' => array(
        'title' => __('Mime Types'),
        'description' => __('Select which type of remote link will be included in the summary.'),
        'type' => 'checkboxes',
        'options' => $mime_types,
        'section' => 'rurls_summary_options',
      ),
      'post_types' => array(
        'title' => __('Post Types'),
        'description' => __('Select which post types should be examined for remote urls.'),
        'type' => 'checkboxes',
        'options' => $post_types,
        'section' => 'rurls_summary_options',
      ),
      'comment_summary' => array(
        'title' => __('Comments'),
        'description' => __('Examine and summarize comments content.'),
        'type' => 'checkbox',
        'section' => 'rurls_summary_options',
      ),

      'import_images' => array(
        'title' => __('Import Images into Media Library'),
        'description' => __('If the remote url is an image, sideload it into the WP Media Library'),
        'type' => 'checkbox',
        'section' => 'rurls_summary_options',
      ),
      'image_size' => array(
        'title' => __('Image Size'),
        'description' => __('Size of images displayed in summary.'),
        'type' => 'select',
        'options' => array_combine( $image_sizes, $image_sizes ),
        'section' => 'rurls_summary_options',
      ),
    );

    $fields = apply_filters( 'rurls-settings-fields', $fields );

    // some simple pre-processing
    foreach ( $fields as $key => &$field ) {
      $field['key'] = $key;
      $field['name'] = RURLS_SETTINGS_NAME . '[' . $key . ']';
    }

    return $fields;
  }

  /**
   * Implements hook admin_init to register our settings
   */
  function admin_init() {
    register_setting( $this->settings_field_group, RURLS_SETTINGS_NAME, array( $this, 'sanitize_settings' ) );

    add_settings_section( 'rurls_summary_options',
      __('Options'),
      array( $this, 'rurls_summary_options_description' ),
      $this->options_page_name
    );

    // preprocess fields and add them to the page
    foreach ( $this->settings_fields as $key => $field ) {
      $field['key'] = $key;
      $field['name'] = RURLS_SETTINGS_NAME . '[' . $key . ']';

      // make sure each key exists in the settings array
      if ( ! isset( $this->settings[ $key ] ) ){
        $this->settings[ $key ] = null;
      }

      // determine appropriate output callback
      switch ( $field['type'] ) {
        case 'checkboxes':
          $callback = 'do_checkboxes';
          break;

        case 'checkbox':
          $callback = 'do_checkbox';
          break;

        case 'select':
          $callback = 'do_select';
          break;

        case 'text':
        default:
          $callback = 'do_text_field';
          break;
      }

      // add the field
      add_settings_field( $key, $field['title'],
        array( $this, $callback ),
        $this->options_page_name,
        $field['section'],
        $field
      );
    }
  }

  /**
   * Implements hook admin_menu to add our options/settings page to the
   *  dashboard menu
   */
  function admin_menu() {
    add_options_page(
      __('Remote URL Summary'),
      __('Remote URL Summary'),
      'manage_options',
      $this->options_page_name,
      array( $this, 'settings_page') );
  }

  /**
   * Output the options/settings page
   */
  function settings_page() {
    ?>
    <div class="wrap">
      <h2><?php print esc_html( get_admin_page_title() ); ?></h2>
      <form method="post" action="options.php">
        <?php
        settings_fields( $this->settings_field_group );
        do_settings_sections( $this->options_page_name );
        submit_button();
        ?>
      </form>
    </div>
    <?php
    //
  }

  // Section description callback
  function rurls_summary_options_description() {
    _e('Additional settings for managing the summaries.');
  }

  /**
   * Sanitization callback for settings/option page
   *
   * @param $input - submitted settings values
   * @return array
   */
  function sanitize_settings( $input ) {
    $options = array();

    // loop through settings fields to control what we're saving
    foreach ( $this->settings_fields as $key => $field ) {
      if ( isset( $input[ $key ] ) ){
        // arrays
        if ( is_array( $input[ $key ] ) ) {
          array_walk( $input[ $key ], 'sanitize_text_field');
          $options[ $key ] = $input[ $key ]; 
        }
        // otherwise, assume string
        else {
          $options[ $key ] = sanitize_text_field( trim( $input[ $key ] ) ); 
        }
      }
      else {
        $options[ $key ] = '';
      }
    }

    return $options;
  }

  /**
   * Output a standard text field
   *
   * @param $field
   */
  function do_text_field( $field ) {
    ?>
    <input type="text"
           id="<?php print esc_attr( $field['key'] ); ?>"
           class="large-text"
           name="<?php print esc_attr( $field['name'] ); ?>"
           value="<?php print esc_attr( $this->settings[ $field['key'] ] ); ?>">
    <?php
    $this->do_field_description( $field );
  }

  /**
   * Output a group of checkboxes
   * 
   * @param $field
   */
  function do_checkboxes( $field ){
    $field_settings = isset( $this->settings[ $field['key'] ] ) ? $this->settings[ $field['key'] ] : array();
    
    foreach ( $field['options'] as $option_key => $option_title ){
      $option_name = $field['name'] . "[$option_key]";
      $option_id = $field['key'] . "-$option_key";
      
      // simple default value
      if ( ! isset($field_settings[ $option_key ]) ){
        $field_settings[ $option_key ] = null;
      }
      
      ?>
      <div class="checkboxes-row">
        <input type="hidden" name="<?php print esc_attr( $option_name ); ?>" value="0">
        <label for="<?php print esc_attr( $option_id ); ?>">
          <input type="checkbox"
                 id="<?php print esc_attr( $option_id ); ?>"
                 name="<?php print esc_attr( $option_name ); ?>"
                 value="1"
            <?php checked( $field_settings[ $option_key ] , 1 ); ?>>
          <?php echo ucfirst( $option_title ); ?>
        </label>
      </div>
      <?php
    }
    $this->do_field_description( $field );
  }
  
  /**
   * Output a checkbox for a boolean setting
   *  - hidden field is default value so we don't have to check isset() on save
   *
   * @param $field
   */
  function do_checkbox( $field ) {
    ?>
    <input type="hidden" name="<?php print esc_attr( $field['name'] ); ?>" value="0">
    <input type="checkbox"
           id="<?php print esc_attr( $field['key'] ); ?>"
           name="<?php print esc_attr( $field['name'] ); ?>"
           value="1"
      <?php checked( $this->settings[ $field['key'] ] , 1 ); ?>>
    <?php
    $this->do_field_description( $field );
  }

  /**
   * Output a select field
   * 
   * @param $field
   */
  function do_select( $field ) {
    $current_value = ( $this->settings[ $field['key'] ] ? $this->settings[ $field['key'] ] : '');
    ?>
    <select name="<?php print esc_attr( $field['name'] ); ?>">
      <?php foreach ( $field['options'] as $value => $text ): ?>
        <option value="<?php print esc_attr( $value ); ?>" <?php selected( $value, $current_value ); ?>><?php print esc_html( $text ); ?></option>
      <?php endforeach; ?>
    </select>
    <?php
    $this->do_field_description( $field );
  }

  /**
   * Output the field description, and example if present
   *
   * @param $field
   */
  function do_field_description( $field ){
    ?>
    <p class="description">
      <?php print $field['description']; ?>
      <?php if ( isset( $field['example'] ) ) : ?>
        <br /><strong><?php _e( 'Example' ); ?>: </strong><code><?php print $field['example']; ?></code>
      <?php endif; ?>
    </p>
    <?php
    //
  }
}