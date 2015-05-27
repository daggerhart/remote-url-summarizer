<?php

/**
 * Determine if any, or a specific, post_type is enabled
 * @param null $post_type
 * @return bool
 */
function rurls_post_type_enabled( $post_type = null ){
  $rurls = Remote_URL_Summarizer::get_instance();
  $settings = $rurls->get_settings();
  
  $enabled = array();

  foreach ( $settings['post_types'] as $type => $value ){
    $value = (int) $value;
    if ( !empty( $value ) ) {
      $enabled[] = $type;
    }
  }

  // if nothing passed into the function, determine if Any type is enabled
  if ( is_null( $post_type ) && count( $enabled ) ){
    return true;
  }
  // look for specific post type
  else if ( ! is_null( $post_type ) && in_array( $post_type, $enabled ) ) {
    return true;
  }

  return false;
}

/**
 * @return mixed|void
 */
function rurls_get_mime_types(){
  $mime_types = apply_filters('rurls-mime-types', array());
  
  return $mime_types;
}

/**
 * Copied from wp-admin/includes/media.php
 *  - slightly modified to allow 'id' as a return option
 *
 * Download an image from the specified URL and attach it to a post.
 *
 * @since 2.6.0
 *
 * @param string $file The URL of the image to download
 * @param int $post_id The post ID the media is to be associated with
 * @param string $desc Optional. Description of the image
 * @param string $return Optional. What to return: an image tag (default) or only the src.
 * @return string|WP_Error Populated HTML img tag on success
 */
function rurls_media_sideload_image( $file, $post_id, $desc = null, $return = 'html' ) {
  // make sure we have all the functions we need to do a complete sideload
  // from the front end (comments)
  if ( ! function_exists( 'media_sideload_image' ) ) {
    require_once ABSPATH . '/wp-admin/includes/media.php';
    require_once ABSPATH . '/wp-admin/includes/file.php';
    require_once ABSPATH . '/wp-admin/includes/image.php';
  }
  
  if ( ! empty( $file ) ) {
    // Set variables for storage, fix file filename for query strings.
    preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
    $file_array = array();
    $file_array['name'] = basename( $matches[0] );

    // Download file to temp location.
    $file_array['tmp_name'] = download_url( $file );

    // If error storing temporarily, return the error.
    if ( is_wp_error( $file_array['tmp_name'] ) ) {
      return $file_array['tmp_name'];
    }

    // Do the validation and storage stuff.
    $id = media_handle_sideload( $file_array, $post_id, $desc );

    // If error storing permanently, unlink.
    if ( is_wp_error( $id ) ) {
      @unlink( $file_array['tmp_name'] );
      return $id;
    }
    else if ( $return === 'id' ) {
      return $id;
    }

    $src = wp_get_attachment_url( $id );
  }

  // Finally check to make sure the file has been saved, then return the HTML.
  if ( ! empty( $src ) ) {
    if ( $return === 'src' ) {
      return $src;
    }

    $alt = isset( $desc ) ? esc_attr( $desc ) : '';
    $html = "<img src='$src' alt='$alt' />";
    return $html;
  } else {
    return new WP_Error( 'image_sideload_failed' );
  }
}