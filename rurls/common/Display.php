<?php

namespace Rurls\Common;

class Display {
  
  private $settings = array();
  
  function __construct( $settings ){
    $this->settings = $settings;

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
   * Implements filter 'get_comment_text'
   * 
   * @param $content
   * @param $comment
   * @return mixed
   */
  function comment_text( $content, $comment ){
    // only enabled post_types
    if ( rurls_post_type_enabled( get_post_type( $comment->comment_post_ID ) ) ) {
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
      <div class="rurls-summary"><?php print $summary; ?></div>
    <?php
    return ob_get_clean();
  }
}