<?php

namespace Rurls\Common;

class Display {
  
  private $settings = array();
  
  function __construct( $settings ){
    $this->settings = $settings;

    if ( $this->settings['default_stylesheet'] ) {
      add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_default_stylesheet' ) );
    }
    
    // post type hooks
    if ( rurls_post_type_enabled() ){
      add_filter( 'the_content', array( $this, 'the_content' ), 9999 );
    }

    // comment hooks
    if ( $this->settings['comment_summary'] ){
      add_filter( 'comment_text', array( $this, 'comment_text' ), 9999, 2 );
    }
  }

  /**
   * 
   */
  function enqueue_default_stylesheet(){
    wp_enqueue_style( 'rurls-default', RURLS_PLUGIN_URL . '/rurls/css/rurls-default.css', array(), RURLS_VERSION );
  }

  /**
   * Implements filter 'the_content'
   * 
   * @param $content
   * @return string
   */
  function the_content( $content ){
    // only enabled post_types
    if ( rurls_post_type_enabled( get_post_type( get_the_ID() ) ) ) {
      $data = get_post_meta( get_the_ID(), RURLS_META_KEY_DATA, true);

      if ( !empty( $data ) ){
        $content.= $this->get_summary( $data );
      }
    }
    
    return $content;
  }

  /**
   * Implements filter 'comment_text'
   * Warning: 'comment_text' filter used different depending on when executed
   * 
   * @param $content
   * @param $comment (optional) but required to work for rurls
   *        /wp-includes/comment.php - does not pass the $comment object, only the content
   *        /wp-includes/comment-template.php - passes $content, $comment, $args
   * 
   * @return mixed
   */
  function comment_text( $content, $comment = false ){
    // only enabled post_types
    if ( $comment && rurls_post_type_enabled( get_post_type( $comment->comment_post_ID ) ) ) {
      $data = get_comment_meta( $comment->comment_ID, RURLS_META_KEY_DATA, true );
      
      if ( !empty( $data ) ){
        $content.= $this->get_summary( $data );
      }
    }
    
    return $content;
  }

  /**
   * Manage the mimetypes in constructing their own content
   * 
   * @param $data
   * @return string
   */
  function get_summary( $data ){
    $summary = '';
    
    foreach ( rurls_get_mime_types() as $type => $details ){
      if ( ! isset( $this->settings['mime_types'][ $type ] ) || (int) $this->settings['mime_types'][ $type ] != 1 ) {
        continue;
      }
      
      if ( ! isset( $data[ $type ] ) || empty( $data[ $type ] ) ) {
        continue;
      }
      
      // execute the displaying of content
      if ( isset( $details['display_callback'] ) && is_callable( $details['display_callback'] )  ) {
        // execute the callback
        $summary.= call_user_func_array( $details['display_callback'], array( 'data' => $data[ $type ] ) );
      }
    }
    
    // wrap the summary and return
    ob_start();
    ?>
      <div class="rurls-summary"><?php print wp_kses_post( $summary ); ?></div>
    <?php
    return ob_get_clean();
  }
}