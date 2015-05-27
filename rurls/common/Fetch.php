<?php

namespace Rurls\Common;

class Fetch {
  
  private $settings;
  
  function __construct( $settings ){
    $this->settings = $settings;
    
    // post type hooks
    if ( rurls_post_type_enabled() ){
      add_action( 'save_post', array( $this, 'save_post' ) );
    }

    // comment hooks
    if ( $this->settings['comment_summary'] ){
      add_action( 'comment_post', array( $this, 'save_comment' ) );
    }
  }

  /**
   * Depending on where this is happening, 
   * we may need to load some core WP files for fetching media
   */
  function load_required_media_files(){
    if ( ! function_exists( 'media_sideload_image' ) ) {
      require_once ABSPATH . '/wp-admin/includes/media.php';
      require_once ABSPATH . '/wp-admin/includes/file.php';
      require_once ABSPATH . '/wp-admin/includes/image.php';
    }
  }

  /**
   * Implements action hook 'save_post'
   *
   * @param $post_id
   */
  function save_post( $post_id ){
    // ignore auto-draft, autosave, and revisions
    if ( get_post_status( $post_id ) == 'auto-draft' ) {
      return;
    } 
    if ( wp_is_post_revision( $post_id ) ){
      return;
    }  
    if ( wp_is_post_autosave($post_id) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
      return;
    }
    
    // make sure we have the required functions
    $this->load_required_media_files();

    $scanned = get_post_meta( $post_id, RURLS_META_KEY_SCANNED, true );
    $scanned = $scanned ? true : false;

    // only scan if never scanned before
    if ( $scanned ){
      return;
    }

    // make sure this post_type is enabled
    if ( ! rurls_post_type_enabled( get_post_type( $post_id ) ) ){
      return;
    }

    // get that post yo!
    $post = get_post( $post_id );

    // get the urls
    $remote_urls = $this->find_remote_urls( $post->post_content );
    
    // if links are found, sort them
    if ( $remote_urls ) {
      $sorted_remote_urls = $this->sort_remote_urls( $remote_urls );

      // if execute the appropriate fetch_callbacks
      if ( ! empty( $sorted_remote_urls ) ) {
        $fetched_data = $this->fetch_remote_data( $sorted_remote_urls );
        
        // save fetched data according to the mimetype handler
        update_post_meta( $post_id, RURLS_META_KEY_DATA, $fetched_data );
      }

      // save list of urls scanned
      update_post_meta( $post_id, RURLS_META_KEY_URLS, $sorted_remote_urls );
    }
    
    // save a boolean so we know this has been done before
    update_post_meta( $post_id, RURLS_META_KEY_SCANNED, true );
  }

  /**
   * Implement action comment_post
   * 
   * @param $comment_id
   */
  function save_comment( $comment_id ){
    // make sure we have the required functions
    $this->load_required_media_files();
    
    $scanned = get_comment_meta( $comment_id, RURLS_META_KEY_SCANNED, true );
    $scanned = $scanned ? true : false;

    // only scan links if never scanned before
    if ( $scanned ) {
      return;
    }
    
    // grab the comment itself
    $comment = get_comment( $comment_id );

    // make sure this post_type is enabled
    if ( ! rurls_post_type_enabled( get_post_type( $comment->comment_post_ID ) ) ){
      return;
    }

    // get the urls
    $remote_urls = $this->find_remote_urls( $comment->comment_content );

    // if links are found, sort them
    if ( $remote_urls ) {
      $sorted_remote_urls = $this->sort_remote_urls( $remote_urls );

      // execute the appropriate fetch_callbacks
      if ( ! empty( $sorted_remote_urls ) ) {
        $fetched_data = $this->fetch_remote_data( $sorted_remote_urls );
        
        // save fetched data according to the mimetype handler
        update_comment_meta( $comment_id, RURLS_META_KEY_DATA, $fetched_data );
      }

      // save list of urls scanned
      update_comment_meta( $comment_id, RURLS_META_KEY_URLS, $sorted_remote_urls );
    }

    // save a boolean so we know this has been done before
    update_comment_meta( $comment_id, RURLS_META_KEY_SCANNED, true );
  }

  /**
   * Scrap the content to find remote urls
   * 
   * @param $content
   * @return array|bool
   */
  function find_remote_urls( $content ){
    $all_urls = array();
    $result = preg_match_all('/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $content, $all_urls);
    $remote_urls = array();


    if ( $result ) {
      foreach ( $all_urls[0] as $i => $url ) {
        if ( $this->is_remote_url( $url ) ) {
          $remote_urls[] = $url;
        }
      }

      if ( !empty( $remote_urls ) ) {
        return $remote_urls;
      }
      else {
        return false;
      }
    }
    else {
      return false;
    }
  }

  /**
   * Given an array of URLs, download each's header to determine mimetype
   *
   * @param $urls array
   * @return array
   */
  function sort_remote_urls( $urls ){
    $sorted_urls = array();

    foreach( $urls as $url ){
      $url = trim( $url );

      // don't fetch local
      if ( $this->is_remote_url( $url ) ) {

        // grab the file header for determining type
        $response = wp_remote_head( $url );

        // no errors allowed
        if ( is_wp_error( $response ) ) {
          continue;
        }

        // only legit urls
        if ( $response['response']['code'] != '200' ) {
          continue;
        }

        // we only care about the content-type for sorting
        if ( isset( $response['headers']['content-type'] ) ) {
          $mime_type = explode(';', $response['headers']['content-type']);
          // images
          $sorted_urls[ $mime_type[0] ][] = $url;
        }
      }
    }

    return $sorted_urls;
  }

  /**
   * Execute the appropriate mimetype's fetch_callback
   * 
   * @param $sorted_remote_urls
   * @return array
   */
  function fetch_remote_data( $sorted_remote_urls ){
    $fetched_data = array();

    foreach ( rurls_get_mime_types() as $type => $details ) {
      if ( ! isset( $sorted_remote_urls[ $type ] ) ) {
        continue;
      }

      if ( ! isset( $this->settings['mime_types'][ $type ] ) || (int) $this->settings['mime_types'][ $type ] != 1 ) {
        continue;
      }

      // execute the fetching of content
      if ( isset( $details['fetch_callback'] ) && is_callable( $details['fetch_callback'] )  ) {
        // allow alteration of the found urls
        $urls = apply_filters( 'rurls-pre-fetch', $sorted_remote_urls[ $type ] );

        // execute the callback
        // it is the callback's job to do any fetching required to save the content
        // and return an array of data of results to be saved 
        $data = call_user_func_array( $details['fetch_callback'], array( 'urls' => $urls ) );

        // allow final alterations of the fetched media/content
        $fetched_data[ $type ] = apply_filters( 'rurls-post-fetch', $data );
      }
    }
    
    return $fetched_data;
  }
  
  /**
   * Determine if a url is external to the current website
   *
   * @param $url
   * @return bool
   */
  function is_remote_url( $url ) {
    $site = parse_url( site_url(), PHP_URL_HOST );
    $other = parse_url( $url, PHP_URL_HOST );

    if ( substr( $site, 0, 3 ) == 'www.' ) {
      $site = substr( $site, 4 );
    }

    if ( substr( $other, 0, 3 ) == 'www.' ) {
      $other = substr( $other, 4 );
    }

    return strtolower( $site ) != strtolower( $other );
  }  
}