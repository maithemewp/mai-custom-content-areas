<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Checks if current page is an singular.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maicca_is_singular() {
	return is_singular();
	// return is_singular() || is_404();
}

/**
 * Checks if current page is an archive.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maicca_is_archive() {
	return is_home() || is_post_type_archive() || is_category() || is_tag() || is_tax() || is_search() || maicca_is_product_archive();
}

/**
 * Checks if current page is a WooCommerce shop.
 *
 * @since 1.2.0
 *
 * @return bool
 */
function maicca_is_shop_archive() {
	return class_exists( 'WooCommerce' ) && is_shop();
}

/**
 * Checks if current page is a WooCommerce product archive.
 *
 * @since 1.2.0
 *
 * @return bool
 */
function maicca_is_product_archive() {
	return class_exists( 'WooCommerce' ) && ( is_shop() || is_product_taxonomy() );
}

/**
 * Checks if current page is a WooCommerce single product.
 *
 * @since 1.2.0
 *
 * @return bool
 */
function maicca_is_product_singular() {
	return class_exists( 'WooCommerce' ) && is_product();
}

/**
 * Checks if a post is a theme content area,
 * registered via config.php.
 *
 * @since 0.1.0
 *
 * @param int $post_id The post ID to check.
 *
 * @return bool
 */
function maicca_is_custom_content_area( $post_id ) {
	if ( 'mai_template_part' !== get_post_type( $post_id ) ) {
		return false;
	}

	$slugs = function_exists( 'mai_get_config' ) ? mai_get_config( 'template-parts' ) : [];

	if ( ! $slugs ) {
		return false;
	}

	$slug   = get_post_field( 'post_name', $post_id );
	$config = $slug && isset( $slugs[ $slug ] );

	return ! $config;
}

/**
 * If user can view content area.
 *
 * @since 0.1.0
 *
 * @param array $args The cca args.
 *
 * @return bool
 */
function maicca_can_view( $args ) {
	// Bail if no id, content, and location.
	if ( ! ( $args['id'] && $args['location'] && $args['content'] ) ) {
		return false;
	}

	// Set variables.
	$locations = maicca_get_locations();
	$status    = get_post_status( $args['id'] );

	// Bail if no location hook. Only check isset for location since 'content' has no hook.
	if ( ! isset( $locations[ $args['location'] ] ) ) {
		return false;
	}

	// Bail if not a status we want.
	if ( ! in_array( $status, [ 'publish', 'private' ] ) ) {
		return false;
	}

	// Bail if user can't view private cca.
	if ( 'private' === $status && ! ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) ) {
		return false;
	}

	return true;
}

/**
 * Gets available post types for content areas.
 *
 * @since 0.1.0
 *
 * @return array
 */
function maicca_get_post_types() {
	static $post_types = null;

	if ( ! is_null( $post_types ) ) {
		return $post_types;
	}

	$post_types = get_post_types( [ 'public' => true ], 'names' );
	unset( $post_types['attachment'] );

	$post_types = apply_filters( 'maicca_post_types', array_values( $post_types ) );

	$post_types = array_unique( array_filter( (array) $post_types ) );

	foreach ( $post_types as $index => $post_type ) {
		if ( post_type_exists( $post_type ) ) {
			continue;
		}

		unset( $post_types[ $index ] );
	}

	return array_values( $post_types );
}

/**
 * Gets post type choices with name => label.
 * If two share the same label, the name is appended in parenthesis.
 *
 * @since 0.1.0
 *
 * @return array
 */
function maicca_get_post_type_choices() {
	static $choices = null;

	if ( ! is_null( $choices ) ) {
		return $choices;
	}

	$choices = maicca_get_post_types();
	$choices = function_exists( 'acf_get_pretty_post_types' ) ? acf_get_pretty_post_types( $choices ) : $choices;

	return $choices;
}

/**
 * Gets taxonomies
 *
 * @since 0.1.0
 *
 * @return array
 */
function maicca_get_taxonomies() {
	static $taxonomies = null;

	if ( ! is_null( $taxonomies ) ) {
		return $taxonomies;
	}

	$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );

	$taxonomies = apply_filters( 'maicca_taxonomies', array_values( $taxonomies ) );

	$taxonomies = array_unique( array_filter( (array) $taxonomies ) );

	foreach ( $taxonomies as $index => $taxonomy ) {
		if ( taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		unset( $taxonomies[ $index ] );
	}

	return array_values( $taxonomies );
}

/**
 * Gets taxonomy choices with name => label.
 * If two share the same label, the name is appended in parenthesis.
 *
 * @since 0.1.0
 *
 * @return array
 */
function maicca_get_taxonomy_choices() {
	static $choices = null;

	if ( ! is_null( $choices ) ) {
		return $choices;
	}

	$choices = maicca_get_taxonomies();
	$choices = function_exists( 'acf_get_pretty_taxonomies' ) ? acf_get_pretty_taxonomies( $choices ) : $choices;

	return $choices;
}

/**
 * Gets DOMDocument object.
 *
 * @since 0.1.0
 *
 * @link https://stackoverflow.com/questions/29493678/loadhtml-libxml-html-noimplied-on-an-html-fragment-generates-incorrect-tags
 *
 * @param string $html Any given HTML string.
 *
 * @return DOMDocument
 */
function maicca_get_dom_document( $html ) {
	// Mai Engine owns the canonical implementation, in lib/functions/utilities.php. Prefer it
	// so there is one copy to maintain. The local fallback below exists only for the case
	// where this plugin somehow runs without it, and must stay behavior-identical.
	if ( function_exists( 'mai_get_dom_document' ) ) {
		return mai_get_dom_document( $html );
	}

	// Create the new document.
	$dom = new DOMDocument();

	// Modify state.
	$libxml_previous_state = libxml_use_internal_errors( true );

	// Encode.
	$html = mb_encode_numericentity( $html, [0x80, 0x10FFFF, 0, ~0], 'UTF-8' );

	// Load the content in the document HTML.
	$dom->loadHTML( "<div>$html</div>" );

	// Handle wraps.
	$container = $dom->getElementsByTagName('div')->item(0);
	$container = $container->parentNode->removeChild( $container );

	while ( $dom->firstChild ) {
		$dom->removeChild( $dom->firstChild );
	}

	while ( $container->firstChild ) {
		$dom->appendChild( $container->firstChild );
	}

	// Handle errors.
	libxml_clear_errors();

	// Restore.
	libxml_use_internal_errors( $libxml_previous_state );

	return $dom;
}

/**
 * Saves HTML from DOMDocument and decodes entities, except those that would turn escaped
 * text back into live markup.
 *
 * @since 1.9.6
 * @since 1.11.0 Stop decoding entities that would produce markup characters.
 *
 * @param DOMDocument $dom
 *
 * @return string
 */
function maicca_get_dom_html( $dom ) {
	// Mai Engine owns the canonical implementation, in lib/functions/utilities.php. Prefer it
	// so there is one copy to maintain.
	if ( function_exists( 'mai_get_dom_html' ) ) {
		return mai_get_dom_html( $dom );
	}

	// Fallback for running without Mai Engine. This MUST stay behavior-identical to the
	// canonical version: it is the live code path on those sites, not dead code.
	//
	// This previously ran mb_convert_encoding( $html, 'UTF-8', 'HTML-ENTITIES' ), which
	// decoded everything and so un-escaped what DOMDocument had deliberately escaped. Text an
	// author typed as &lt;script&gt; came back out as a live script tag, and JSON in a data
	// attribute was broken out of its own quotes. Decode by result instead: anything that
	// would decode to a character capable of creating markup or ending an attribute value
	// stays escaped, everything else decodes as before.
	static $structural = [ '<', '>', '"', "'" ];

	return preg_replace_callback(
		'/&(?:#[0-9]+|#[xX][0-9a-fA-F]+|[A-Za-z][A-Za-z0-9]*);/',
		static function ( $match ) use ( $structural ) {
			$decoded = html_entity_decode( $match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			// Unknown entity: html_entity_decode hands it back unchanged. Leave it alone.
			if ( $decoded === $match[0] ) {
				return $match[0];
			}

			return in_array( $decoded, $structural, true ) ? $match[0] : $decoded;
		},
		$dom->saveHTML()
	);
}

/**
 * Adds content area to existing content/HTML.
 *
 * @access private
 *
 * @since 0.1.0
 *
 * @uses DOMDocument
 *
 * @param string $content     The existing html.
 * @param string $cca_content The content area html.
 * @param array  $args        The cca args.
 *
 * @return string.
 */
function maicca_add_cca( $content, $cca_content, $args ) {
	$cca_content  = trim( $cca_content );
	$args         = wp_parse_args( $args,
		[
			'location' => 'after', // The location of the cca in relation to the elements.
			'count'    => 6,       // The amount of elements to count before showing the content area.
		]
	);

	// Sanitize.
	$location = esc_html( $args['location'] );
	$after    = 'before' !== $location;
	$count    = absint( $args['count'] );

	if ( ! ( trim( $content ) && $cca_content && $count ) ) {
		return $content;
	}

	$dom   = maicca_get_dom_document( $content );
	$xpath = new DOMXPath( $dom );
	$all   = $xpath->query( '/*[not(self::script or self::style or self::link)]' );

	if ( ! $all->length ) {
		return $content;
	}

	$last     = $all->item( $all->length - 1 );
	$tags     = $after ? [ 'div', 'p', 'ol', 'ul', 'blockquote', 'figure', 'iframe' ] : [ 'h2', 'h3' ];
	$tags     = apply_filters( 'maicca_content_elements', $tags, $location );
	$tags     = array_filter( $tags );
	$tags     = array_unique( $tags );
	$elements = [];

	foreach ( $all as $node ) {
		if ( ! $node->childNodes->length || ! in_array( $node->nodeName, $tags ) ) {
			continue;
		}

		$elements[] = $node;
	}

	if ( ! $elements ) {
		return $content;
	}

	// Process the CCA.
	$cca_content = maicca_get_processed_content( $cca_content );

	if ( ! $cca_content ) {
		return $content;
	}

	/**
	 * Build the temporary dom.
	 * Special characters were causing issues with `appendXML()`.
	 *
	 * @link https://stackoverflow.com/questions/4645738/domdocument-appendxml-with-special-characters
	 * @link https://www.py4u.net/discuss/974358
	 */
	$tmp    = maicca_get_dom_document( $cca_content );
	$insert = [];

	foreach ( $tmp->childNodes as $node ) {
		if ( ! $node instanceof DOMElement ) {
			continue;
		}

		$insert[] = $dom->importNode( $node, true );
	}

	if ( ! $insert ) {
		return $content;
	}

	// Reverse the array if displaying after or prepending, so they end up in the right order.
	// After would put each element directly after the target, so they would end up in reverse order.
	// Prepend would put each element directly before the first child of the target, so they would end up in reverse order.
	if ( $after ) {
		$insert = array_reverse( $insert );
	}

	// Filter only DOMElement nodes from array.
	$insert = array_filter( $insert, function( $node ) {
		return $node instanceof DOMElement;
	});

	if ( ! $insert ) {
		return $content;
	}

	$item = 0;

	foreach ( $elements as $index => $element ) {
		$item++;

		if ( $count !== $item ) {
			continue;
		}

		// After elements.
		if ( $after ) {
			/**
			 * Bail if this is the last element.
			 * This avoids duplicates since this location would technically be "after entry content" at this point.
			 */
			if ( $element === $last || null === $element->nextSibling ) {
				break;
			}

			foreach ( $insert as $node ) {
				/**
				 * Add cca after this element. There is no insertAfter() in PHP ¯\_(ツ)_/¯.
				 *
				 * @link https://gist.github.com/deathlyfrantic/cd8d7ef8ba91544cdf06
				 */
				$element->parentNode->insertBefore( $node, $element->nextSibling );
			}

		}
		// Before headings.
		else {

			foreach ( $insert as $node ) {
				$element->parentNode->insertBefore( $node, $element );
			}
		}

		// No need to keep looping.
		break;
	}

	// Save new HTML.
	$content = maicca_get_dom_html( $dom );

	return $content;
}

/**
 * Insert a value or key/value pair after a specific key in an array.
 * If key doesn't exist, value is appended to the end of the array.
 *
 * @since 0.1.0
 *
 * @param array  $array
 * @param string $key
 * @param array  $new
 *
 * @return array
 */
function maiam_array_insert_after( array $array, $key, array $new ) {
	$keys  = array_keys( $array );
	$index = array_search( $key, $keys, true );
	$pos   = false === $index ? count( $array ) : $index + 1;

	return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
}

/**
 * Get processed content.
 * Take from mai_get_processed_content() in Mai Engine.
 *
 * @since 0.1.0
 *
 * @return string
 */
function maicca_get_processed_content( $content ) {
	if ( function_exists( 'mai_get_processed_content' ) ) {
		return mai_get_processed_content( $content );
	}

	/**
	 * Embed.
	 *
	 * @var WP_Embed $wp_embed Embed object.
	 */
	global $wp_embed;

	$blocks  = has_blocks( $content );
	$content = $wp_embed->autoembed( $content );           // WP runs priority 8.
	$content = $wp_embed->run_shortcode( $content );       // WP runs priority 8.
	$content = $blocks ? do_blocks( $content ) : $content; // WP runs priority 9.
	$content = wptexturize( $content );                    // WP runs priority 10.
	$content = ! $blocks ? wpautop( $content ) : $content; // WP runs priority 10.
	$content = shortcode_unautop( $content );              // WP runs priority 10.
	$content = function_exists( 'wp_filter_content_tags' ) ? wp_filter_content_tags( $content ) : wp_make_content_images_responsive( $content ); // WP runs priority 10. WP 5.5 with fallback.
	$content = do_shortcode( $content );                   // WP runs priority 11.
	$content = convert_smilies( $content );                // WP runs priority 20.

	return $content;
}

/**
 * Sanitizes keyword strings to array.
 *
 * @since 0.1.0
 *
 * @param string $keywords Comma-separated keyword strings.
 *
 * @return array
 */
function maicca_sanitize_keywords( $keywords ) {
	$sanitized = [];
	$keywords  = trim( (string) $keywords );

	if ( ! $keywords ) {
		return $sanitized;
	}

	$sanitized = explode( ',', $keywords );
	$sanitized = array_map( 'trim', $sanitized );
	$sanitized = array_filter( $sanitized );
	$sanitized = array_map( 'maicca_strtolower', $sanitized );

	return $sanitized;
}

/**
 * Sanitized a string to lowercase, keeping character encoding.
 *
 * @since 0.1.0
 *
 * @param string $string The string to make lowercase.
 *
 * @return string
 */
function maicca_strtolower( $string ) {
	return mb_strtolower( (string) $string, 'UTF-8' );
}

/**
 * Sanitizes taxonomy data for CCA.
 *
 * @since 0.1.0
 *
 * @param array $taxonomies The taxonomy data.
 *
 * @return array
 */
function maicca_sanitize_taxonomies( $taxonomies ) {
	if ( ! $taxonomies ) {
		return $taxonomies;
	}

	$sanitized = [];

	foreach ( $taxonomies as $data ) {
		$args = wp_parse_args( $data,
			[
				'taxonomy' => '',
				'terms'    => [],
				'operator' => 'IN',
			]
		);

		// Skip if we don't have all of the data.
		if ( ! ( $args['taxonomy'] && $args['terms'] && $args['operator'] ) ) {
			continue;
		}

		$sanitized[] = [
			'taxonomy' => esc_html( $args['taxonomy'] ),
			'terms'    => array_map( 'absint', (array) $args['terms'] ),
			'operator' => esc_html( $args['operator'] ),
		];
	}

	return $sanitized;
}

/**
 * Removes any array elements where the value is an empty string.
 *
 * @access private
 *
 * @since 0.1.0
 *
 * @param array $array The taxonomy data.
 *
 * @return array
 */
function maicca_filter_associative_array( $array ) {
	foreach( $array as $key => $value ) {
		if ( '' === $value ) {
			unset( $array[ $key ] );
		} elseif ( is_array( $value ) ) {
			$array[ $key ] = maicca_filter_associative_array( $value );
		}
	}

	return $array;
}
