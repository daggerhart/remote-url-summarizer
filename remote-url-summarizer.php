<?php
/*
Plugin Name: Remote URL Summarizer
Description: Scan remote / external links with a post or comments, and provide a summary of the scanned links during display.
Plugin URI: https://github.com/daggerhart/remote-url-summarizer
Version: 1.1
Author: daggerhart
Author URI: http://www.daggerhart.com
License: GPLv2 Copyright (c) 2015 daggerhart
*/

define( 'RURLS_VERSION', '1.1' );
define( 'RURLS_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'RURLS_SETTINGS_NAME', 'rurls_settings' );
define( 'RURLS_META_KEY_SCANNED', 'rurls_scanned' );
define( 'RURLS_META_KEY_URLS', 'rurls_remote_urls' );
define( 'RURLS_META_KEY_DATA', 'rurls_data' );

class Remote_URL_Summarizer {

  // default plugin settings values
  private $default_settings = array(
    'mime_types' => array(
      'image/jpeg' => 1,
    ),
    'post_types' => array(
      'post' => 1
    ),
    'image_size' => 'thumbnail',
    'default_stylesheet' => 1,
  );

  // storage for plugin settings
  private $settings = array();

  // singleton instance
  static private $instance = null;
  
  /**
   * Access singleton instance
   * 
   * @return \Remote_URL_Summarizer
   */
  static public function get_instance(){
    if ( is_null( self::$instance ) ){
      self::$instance = new Remote_URL_Summarizer();
    }

    return self::$instance;
  }

  /**
   * Hook into WP init
   */
  private function __construct(){
    add_action( 'init', array( $this, 'init' ) );
  }

  /**
   * Complete the requirements of this plugin
   */
  function init(){
    define( 'RURLS_PLUGIN_URL', plugins_url( basename( RURLS_PLUGIN_DIR ) ) );
    
    // common files
    include_once RURLS_PLUGIN_DIR . '/rurls/helper-functions.php';
    
    // common classes
    new \Rurls\Common\Fetch( $this->get_settings() );
    new \Rurls\Common\Display( $this->get_settings() );
    new \Rurls\Common\Mimetypes\Images( $this->get_settings() );
    new \Rurls\Common\Mimetypes\Html( $this->get_settings() );

    // admin only
    if ( is_admin() ) {
      new \Rurls\Admin\Settings( $this->get_settings() );
    }
  }

  /**
   * Get plugin settings
   *  - settings field logic in admin/settings class
   *
   * @return array
   */
  public function get_settings() {
    if ( ! empty( $this->settings ) ){
      return $this->settings;
    }

    $this->settings = wp_parse_args( get_option( RURLS_SETTINGS_NAME, array() ), $this->default_settings );
    return $this->settings;
  }
}

// autoloader
spl_autoload_register(function ($class) {

  // project-specific namespace prefix
  $prefix = 'Rurls\\';

  // base directory for the namespace prefix
  $base_dir = __DIR__ . '/rurls/';

  // does the class use the namespace prefix?
  $len = strlen($prefix);
  if (strncmp($prefix, $class, $len) !== 0) {
    // no, move to the next registered autoloader
    return;
  }

  // get the relative class name
  $relative_class = substr($class, $len);

  // replace the namespace prefix with the base directory, replace namespace
  // separators with directory separators in the relative class name, append
  // with .php
  $file = strtolower($base_dir . str_replace('\\', '/', $relative_class) . '.php');

  // if the file exists, require it
  if (file_exists($file)) {
    require $file;
  }
});

// What a lovely day!
Remote_URL_Summarizer::get_instance();