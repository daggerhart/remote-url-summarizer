<?php

namespace Rurls\Common\Mimetypes;

class Images {

	private $settings;

	function __construct( $settings ) {
		$this->settings = $settings;

		// register image mimetypes
		add_filter( 'rurls-mime-types', array( $this, 'mime_types' ) );
	}

	/**
	 * Register image mime_types with the system
	 *
	 * @param $mime_types
	 *
	 * @return mixed
	 */
	function mime_types( $mime_types ) {
		$mime_types['image/jpeg'] = array(
			'title'            => __( 'JPEG' ),
			'fetch_callback'   => array( $this, 'sideload_images' ),
			'display_callback' => array( $this, 'display_images' ),
		);
		$mime_types['image/gif']  = array(
			'title'            => __( 'GIF' ),
			'fetch_callback'   => array( $this, 'sideload_images' ),
			'display_callback' => array( $this, 'display_images' ),
		);
		$mime_types['image/png']  = array(
			'title'            => __( 'PNG' ),
			'fetch_callback'   => array( $this, 'sideload_images' ),
			'display_callback' => array( $this, 'display_images' ),
		);

		return $mime_types;
	}

	/**
	 * Loop through an array of image urls and sideload them all
	 *
	 * @param $urls ( url string | array of url strings)
	 *
	 * @return array
	 */
	function sideload_images( $urls ) {
		$images  = array();
		$post_id = get_the_ID() ? get_the_ID() : 0;

		foreach ( $urls as $url ) {
			// attempt to grab the image
			$image_id = rurls_media_sideload_image( $url, $post_id, NULL, 'id' );

			if ( ! is_wp_error( $image_id ) ) {
				$images[ $image_id ] = $url;
			}
		}

		return $images;
	}

	/**
	 * Display callback for default image mimetypes
	 *
	 * @param $data array
	 *
	 * @return string
	 */
	function display_images( $data ) {
		ob_start();
		?>
		<span class="rurls-images">
			<?php foreach ( $data as $id => $original_src ) { ?>
				<span class="rurls-image"><?php print wp_get_attachment_image( $id, $this->settings['image_size'] ); ?></span>
			<?php } ?>
        </span>
		<?php
		return ob_get_clean();
	}
}