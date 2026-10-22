<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Gets a post's ancestor IDs, memoized per request.
 *
 * Core `get_post_ancestors()` re-walks `post_parent` on every call. The
 * ancestor chain is constant for the whole request, and `mai_do_ccas()`
 * loops every content area (each potentially checking ancestors twice),
 * so cache the result per post ID for the request.
 *
 * @since TBD
 *
 * @param int $post_id The post ID.
 *
 * @return int[]
 */
function maicca_get_post_ancestors( $post_id ) {
	static $cache = [];

	if ( ! isset( $cache[ $post_id ] ) ) {
		$cache[ $post_id ] = array_map( 'absint', get_post_ancestors( $post_id ) );
	}

	return $cache[ $post_id ];
}

/**
 * Gets a post's content prepared for keyword matching, memoized per request.
 *
 * `do_shortcode()` + `strip_tags()` over full post content is expensive and
 * was previously run once per keyword-condition content area per request,
 * reprocessing the same post repeatedly. Cache the processed string per post
 * ID for the request.
 *
 * @since TBD
 *
 * @param int $post_id The post ID.
 *
 * @return string
 */
function maicca_get_searchable_content( $post_id ) {
	static $cache = [];

	if ( ! isset( $cache[ $post_id ] ) ) {
		$post              = get_post( $post_id );
		$cache[ $post_id ] = $post ? maicca_strtolower( strip_tags( do_shortcode( trim( $post->post_content ) ) ) ) : '';
	}

	return $cache[ $post_id ];
}

// add_action( 'load-post-new.php', 'maicca_create_display_terms' );
// add_action( 'load-post.php', 'maicca_create_display_terms' );
/**
 * Creates default content type terms.
 *
 * @since 0.1.0
 *
 * @return void
 */
function maicca_create_display_terms() {
	$screen = get_current_screen();

	if ( 'mai_template_part' !== $screen->post_type ) {
		return;
	}

	$post_types = maicca_get_post_type_choices();

	if ( ! $post_types ) {
		return;
	}

	foreach ( $post_types as $name => $label ) {
		if ( term_exists( $name, 'mai_cca_display' ) ) {
			continue;
		}

		$data = wp_insert_term( $label, 'mai_cca_display', [ 'slug' => $name ] );
	}
}
