# Remote URL Summarizer

When a post or comment is saved, this plugin will scan it for remote (external) urls, and grab data about them. When viewing the post or comment, this plugin will create a summary of the remote urls and display it beneath the content. If the remote url is determined to be an image by its mimetype, it will be sideloaded into the WordPress media library and attached to the post.

## Features

* Per post_type support
* Comments support
* Extendable through hooks
* Sideload (download) remote images into WordPress media library
* Select image size to be shown in summary

### Hooks

**Filters**

* `rurls-settings-fields` - Extend the options page to include additional settings
* `rurls-pre-fetch` - Alter the found urls before fetching data 
* `rurls-post-fetch` - Alter the fetched data before saving as post/comment meta
* `rurls-mime-types` - Provide support for additional mime types

### Supported Mime Types 

Included in this plugin is the ability to summarize the following mimetypes.

* image/jpeg
* image/gif
* image/png
* text/html


### Adding Mime Type Support

You can provide additional mime types for summarization using the `rurls-mime-types` filter.  

Simple example:

```php
// provide an additional mime type to the summarizer, along with its fetch and display callbacks
function my_mime_types( $mime_types ){
    $mime_types['image/bmp'] = array(
        'title' => __('BMP'),
        'fetch_callback' => 'my_fetch_callback', // callable string or array
        'display_callback' => 'my_display_callback', // callable string or array
    );
    
    return $mime_types;
}
add_filter('rurls-mime-types', 'my_mime_types');

// fetch_callback needs to loop through the urls and return an array of data
function my_fetch_callback( $urls ){
    $images = array();
    $post_id = get_the_ID() ? get_the_ID() : 0;

    foreach( $urls as $original_url ){
      // attempt to grab the image
      $image_id = rurls_media_sideload_image( $original_url, $post_id, null, 'id');

      if ( ! is_wp_error( $image_id ) ) {
        $images[ $image_id ] = $original_url;
      }
    }

    return $images;
}

// display_callback needs to loop through the data array and return a string of html
function my_display_callback( $data ){
    $output = '';
    foreach( $data as $id => $original_url ){
        $output.= wp_get_attachment_image( $id, $this->settings['image_size'] );
    }
    return $output;
}
```
