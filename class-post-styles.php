<?php
/*
 * Post Styles
 *
 * @package   McNinja_Post_Styles
 * @author    Tom Harrigan <tom29axp@gmail.com>
 * @license   GPL-2.0+
 * @link      http://thomasharrigan.com/mcninja-post-styles
 */


class McNinja_Post_Styles {

	public function __construct() {
		add_post_type_support( 'post', 'post-styles' );
		add_action( 'init', array( $this, 'create_style_taxonomies' ), 0 );
		add_filter( 'post_class', array( $this, 'my_class_names' ) );
		add_action( 'add_meta_boxes', array( $this, 'stylesbox' ) );
		add_action( 'save_post', array( $this, 'post_style_meta_box_save_postdata' ) );
		add_filter( 'request', array( $this, '_post_style_request' ) );
		add_filter( 'term_link', array( $this, '_post_style_link' ), 10, 3 );
		add_filter( 'get_post_style', array( $this, '_post_style_get_term' ) );
		add_filter( 'get_terms', array( $this, '_post_style_get_terms' ), 10, 3 );
		add_filter( 'wp_get_object_terms', array( $this, '_post_style_wp_get_object_terms' ) );
	}

	protected static $instance = null;

	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}
    
	// Add specific CSS class by filter

	public function my_class_names($classes) {
		global $post;
		if ( post_type_supports( $post->post_type, 'post-styles' ) ) {
			$post_style = $this->get_post_style( $post );
			if( !is_single() ) {
				if ( $post_style && ! is_wp_error( $post_style )  )
					$classes[] = 'post-style-' . sanitize_html_class( $post_style );
				else
					$classes[] = 'post-style-standard';
			}
		}
		
		// return the $classes array
		return $classes;
	}

	public function create_style_taxonomies () {
		register_taxonomy( 'post_style', 'post', array(
			'public' => true,
			'hierarchical' => false,
			'labels' => array(
				'name' => _x( 'Post Style', 'post style' ),
				'singular_name' => _x( 'Post Style', 'post style' ),
			),
			'query_var' => true,
			'rewrite' => 'type',
			'show_ui' => false,
			'_builtin' => false,
			'show_in_nav_menus' => true,
		) );
	}

	public function stylesbox ( $post_type ) {
		if( $post_type == 'post' ) {
			add_meta_box( 'stylediv', _x( 'Post Style', 'post style' ), array( $this, 'post_style_meta_box' ), null, 'side', 'default', 0 );
		}
	}

	/**
	 * Display post style form elements.
	 *
	 * @param object $post
	 */
	public function post_style_meta_box( $post ) {
		if ( post_type_supports( $post->post_type, 'post-styles' ) ) :
		
		wp_nonce_field( 'post_style_meta_box', 'post_style_meta_box_nonce' );
		
		$post_styles = array_keys( $this->get_post_style_strings() );
		
		array_shift( $post_styles );
		
		if ( is_array( $post_styles ) ) :
			$post_style =  $this->get_post_style( $post->ID );

			if ( !$post_style )
				$post_style = '0';
			// Add in the current one if it isn't there yet, in case the current theme doesn't support it
			if ( $post_style && !in_array( $post_style, $post_styles ) )
				$post_style = '0';
		?>
		<div id="post-styles-select">
			<input type="radio" name="post_style" class="post-style" id="post-style-0" value="0" <?php checked( $post_style, '0' ); ?> /> <label for="post-style-0" class="post-style-icon post-style-standard"><?php echo $this->get_post_style_string( 'standard' ); ?></label>
			<?php foreach ( $post_styles as $style ) : ?>
			<br /><input type="radio" name="post_style" class="post-style" id="post-style-<?php echo esc_attr( $style ); ?>" value="<?php echo esc_attr( $style ); ?>" <?php checked( $post_style, $style ); ?> /> <label for="post-style-<?php echo esc_attr( $style ); ?>" class="post-style-icon post-style-<?php echo esc_attr( $style ); ?>"><?php echo esc_html( $this->get_post_style_string( $style ) ); ?></label>
			<?php endforeach; ?><br />
		</div>
		<?php endif; endif;
	}

	/**
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function post_style_meta_box_save_postdata( $post_id ) {
	  /*
	   * We need to verify this came from the our screen and with proper authorization,
	   * because save_post can be triggered at other times.
	   */

	  // Check if our nonce is set.
	  if ( ! isset( $_POST['post_style_meta_box_nonce'] ) )
	    return $post_id;

	  $nonce = $_POST['post_style_meta_box_nonce'];

	  // Verify that the nonce is valid.
	  if ( ! wp_verify_nonce( $nonce, 'post_style_meta_box' ) )
	      return $post_id;

	  // If this is an autosave, our form has not been submitted, so we don't want to do anything.
	  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
	      return $post_id;

	  // Check the user's permissions.
	  if ( ! current_user_can( 'edit_post', $post_id ) )
	        return $post_id;
	  
	  /* OK, its safe for us to save the data now. */
	  if ( isset( $_POST['post_style'] ) )
			$this->set_post_style( $post_id, $_POST['post_style'] );
	  
	}

	public function get_post_style( $post = null ) {

		if ( ! $post = get_post( $post )  )
			return false;

		if ( ! post_type_supports( $post->post_type, 'post-styles' ) )
			return get_post_type();

		$_style = get_the_terms( $post->ID, 'post_style' );
		if ( empty( $_style ) )
			return get_post_type();

		$style = array_shift( $_style );
		return str_replace('post-style-', '', $style->slug );
	}

	/**
	 * Check if a post has any of the given formats, or any format.
	 *
	 * @uses has_term()
	 *
	 * @param string|array $format Optional. The format or formats to check.
	 * @param object|int $post Optional. The post to check. If not supplied, defaults to the current post if used in the loop.
	 * @return bool True if the post has any of the given formats (or any format, if no format specified), false otherwise.
	 */
	public function has_post_style( $style = array(), $post = null ) {
		$prefixed = array();

		if ( $style ) {
			foreach ( (array) $style as $single ) {
				$prefixed[] = 'post-style-' . sanitize_key( $single );
			}
		}

		return has_term( $prefixed, 'post_style', $post );
	}

	/**
	 * Assign a style to a post
	 *
	 * @param int|object $post The post for which to assign a format.
	 * @param string $format A format to assign. Use an empty string or array to remove all formats from the post.
	 * @return mixed WP_Error on error. Array of affected term IDs on success.
	 */
	public function set_post_style( $post, $style ) {
		$post = get_post( $post );

		if ( empty( $post ) )
			return new WP_Error( 'invalid_post', __( 'Invalid post' ) );

		if ( ! empty( $style ) ) {
			$style = sanitize_key( $style );
			if ( 'standard' === $style || ! in_array( $style, $this->get_post_style_slugs() ) )
				$style = '';
			else
				$style = 'post-style-' . $style;
		}

		return wp_set_post_terms( $post->ID, $style, 'post_style' );
	}

	/**
	 * Returns an array of post style slugs to their translated and pretty display versions
	 *
	 * @return array The array of translated post style names.
	 */
	public function get_post_style_strings() {
		$strings = array(
			'aside' => _x( 'Aside', 'Post style' ),
			'image' => _x( 'Image', 'Post style' ),
			'video' => _x( 'Video', 'Post style' ),
			'audio' => _x( 'Audio', 'Post style' ),
			'quote' => _x( 'Quote', 'Post style' ),
			'link' => _x( 'Link', 'Post style' ),
			'link-list' => _x( 'List of Links', 'Post style' ),
			'gallery' => _x( 'Gallery', 'Post style' ),
			'no-photo' => _x( 'No Photo', 'Post style' ),
		);
		$strings = apply_filters( 'post_style_strings', $strings );
		return array( 'standard' => _x( 'Standard', 'Post style' ) ) + $strings;
	}

	/**
	 * Retrieves an array of post format slugs.
	 *
	 * @uses get_post_format_strings()
	 *
	 * @return array The array of post format slugs.
	 */
	public function get_post_style_slugs() {
		$slugs = array_keys( $this->get_post_style_strings() );
		return array_combine( $slugs, $slugs );
	}

	/**
	 * Returns a pretty, translated version of a post style slug
	 *
	 * @uses get_post_style_strings()
	 *
	 * @param string $slug A post format slug.
	 * @return string The translated post format name.
	 */
	public function get_post_style_string( $slug ) {
		$strings = $this->get_post_style_strings();
		if ( !$slug )
			return $strings['standard'];
		else
			return ( isset( $strings[$slug] ) ) ? $strings[$slug] : '';
	}

	/**
	 * Returns a link to a post format index.
	 *
	 * @param string $format The post format slug.
	 * @return string The post format term link.
	 */
	public function get_post_style_link( $style ) {
		$term = get_term_by('slug', 'post-style-' . $style, 'post_style' );
		if ( ! $term || is_wp_error( $term ) )
			return false;
		return get_term_link( $term );
	}

	/**
	 * Filters the request to allow for the format prefix.
	 *
	 * @access private
	 */
	function _post_style_request( $qvs ) {
		if ( ! isset( $qvs['post_style'] ) )
			return $qvs;
		$slugs = $this->get_post_style_slugs();
		if ( isset( $slugs[ $qvs['post_style'] ] ) )
			$qvs['post_style'] = 'post-style-' . $slugs[ $qvs['post_style'] ];
		$tax = get_taxonomy( 'post_style' );
		if ( ! is_admin() )
			$qvs['post_type'] = $tax->object_type;
		return $qvs;
	}

	/**
	 * Filters the post format term link to remove the format prefix.
	 *
	 * @access private
	 */
	function _post_style_link( $link, $term, $taxonomy ) {
		global $wp_rewrite;
		if ( 'post_style' != $taxonomy )
			return $link;
		if ( $wp_rewrite->get_extra_permastruct( $taxonomy ) ) {
			return str_replace( "/{$term->slug}", '/' . str_replace( 'post-style-', '', $term->slug ), $link );
		} else {
			$link = remove_query_arg( 'post_style', $link );
			return add_query_arg( 'post_style', str_replace( 'post-style-', '', $term->slug ), $link );
		}
	}

	/**
	 * Remove the post format prefix from the name property of the term object created by get_term().
	 *
	 * @access private
	 */
	function _post_style_get_term( $term ) {
		if ( isset( $term->slug ) ) {
			$term->name = $this->get_post_style_string( str_replace( 'post-style-', '', $term->slug ) );
		}
		return $term;
	}

	/**
	 * Remove the post format prefix from the name property of the term objects created by get_terms().
	 *
	 * @access private
	 */
	function _post_style_get_terms( $terms, $taxonomies, $args ) {
		if ( in_array( 'post_style', (array) $taxonomies ) ) {
			if ( isset( $args['fields'] ) && 'names' == $args['fields'] ) {
				foreach( $terms as $order => $name ) {
					$terms[$order] = $this->get_post_style_string( str_replace( 'post-style-', '', $name ) );
				}
			} else {
				foreach ( (array) $terms as $order => $term ) {
					if ( isset( $term->taxonomy ) && 'post_style' == $term->taxonomy ) {
						$terms[$order]->name = $this->get_post_style_string( str_replace( 'post-style-', '', $term->slug ) );
					}
				}
			}
		}
		return $terms;
	}

	/**
	 * Remove the post format prefix from the name property of the term objects created by wp_get_object_terms().
	 *
	 * @access private
	 */
	function _post_style_wp_get_object_terms( $terms ) {
		foreach ( (array) $terms as $order => $term ) {
			if ( isset( $term->taxonomy ) && 'post_style' == $term->taxonomy ) {
				$terms[$order]->name = $this->get_post_style_string( str_replace( 'post-style-', '', $term->slug ) );
			}
		}
		return $terms;
	}

}