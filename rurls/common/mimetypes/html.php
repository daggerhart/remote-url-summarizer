<?php

namespace Rurls\Common\Mimetypes;

class Html {
  
  private $settings;
  
  function __construct( $settings ){
    $this->settings = $settings;
    
    add_filter( 'rurls-mime-types', array( $this, 'mime_types' ) );
  }

  /**
   * Register html mime_type with the system
   */
  function mime_types( $mime_types ){
    $mime_types['text/html'] = array(
      'title' => __('HTML'),
      'fetch_callback' => array( $this, 'fetch_html' ),
      'display_callback' => array( $this, 'display_html' ),
    );

    return $mime_types;
  }

  /**
   * @param $urls
   * @return array
   */
  function fetch_html( $urls ){
    $fetched_data = array();
    
    foreach ( $urls as $i => $url ){
      $response = wp_remote_get( $url );
      
      if ( is_wp_error( $response ) ){
        continue;
      }
      
      if ( isset( $response['body'] ) && $response['response']['code'] == 200 ) {
        $data = $this->get_fetched_data( $response['body'] );
        $data['url'] = $url;
        
        $fetched_data[ $i ] = $data;
      }
    }
    
    return $fetched_data;
  }

  /**
   * Given and html string, find our desired data  
   * 
   * @param $html
   * @return array
   */
  function get_fetched_data( $html ){
    $doc = new \DOMDocument();
    
    // suppress HTML5 warnings
    libxml_use_internal_errors(true);
    
    $doc->loadHTML( $html );

    // restore error messages
    libxml_clear_errors();
    libxml_use_internal_errors(false);
    
    $doc->normalizeDocument();

    // construct a reliable array of data
    $data = array(
      'title' => sanitize_text_field( $doc->getElementsByTagName('title')->item(0)->nodeValue ),
      'description' => '',
      'image' => array(
        'src' => '',   // the original image src
        'id' => false, // the generated image id if successfully sideloaded
      ),
    );

    // get meta nodes and look for og: data
    $metas = $doc->getElementsByTagName('meta');

    // look for opengraph data
    for ($j = 0; $j < $metas->length; $j++) {
      $meta_node = $metas->item($j);

      if ( $meta_node->getAttribute('property') == 'og:description' ){
        $data['description'] = sanitize_text_field( $meta_node->getAttribute('content') );
      }

      if ( $meta_node->getAttribute('property') == 'og:image' ){
        $data['image']['src'] = esc_url_raw( $meta_node->getAttribute('content') );
      }
    }

    // fallback to generic metadata
    if ( empty( $data['description'] ) ){
      for ($j = 0; $j < $metas->length; $j++) {
        $meta_node = $metas->item($j);

        if ( $meta_node->getAttribute('name') == 'description' ){
          $data['description'] = sanitize_text_field( $meta_node->getAttribute('content') );
        }
      }
    }

    // if we got an image, sideload it
    if ( ! empty( $data['image']['src'] ) ){
      $post_id = get_the_ID() ? get_the_ID() : 0;
      $id = rurls_media_sideload_image( $data['image']['src'], $post_id, null, 'id' );

      if ( ! is_wp_error( $id ) ){
        $data['image']['id'] = $id;
      }
    }
    
    return $data;
  }

  /**
   * Loop through each fetched_data array and template output 
   * 
   * @param $fetched_data
   * @return string
   */
  function display_html( $fetched_data ){
    ob_start();
    
    foreach( $fetched_data as $i => $data ){
      if ( ! empty( $data['title'] ) && ! empty( $data['description'] ) ) {
        $this->template_html_row( $data ); 
      }
    }
    
    return ob_get_clean();
  }

  /**
   * Template an html row by providing title, url, image, and description
   * 
   * @param $data
   */
  function template_html_row( $data ){
    ?>
    <div class="rurls-html-row">
      <?php if ( $data['title'] ) { ?>
        <h4 class="rurls-html-title">
          <a href="<?php print esc_url( $data['url'] ); ?>" target="_blank"><?php print esc_html( $data['title'] ); ?></a>
        </h4>
        <div class="rurls-html-url"><?php print esc_url( $data['url'] ); ?></div>
      <?php } ?>
      
      <?php if ( $data['image']['id'] ) { ?>
          <span class="rurls-html-image">
            <?php print wp_get_attachment_image( $data['image']['id'], $this->settings['image_size'] ); ?>
          </span>
      <?php } ?>
      
      <?php if ( $data['description'] ) { ?>
        <p class="rurls-html-description"><?php print esc_html( $data['description'] ); ?></p>
      <?php } ?>
    </div>
    <?php
    //
  }
}