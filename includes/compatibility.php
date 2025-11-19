<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

add_filter( 'maicca_taxonomies', 'maicca_taxonomies' );
/**
 * Adds the Mai Display taxonomy to the taxonomies array.
 *
 * @since TBD
 *
 * @param array $taxonomies The taxonomies to use.
 *
 * @return array
 */
function maicca_taxonomies( $taxonomies ) {
	if ( ! class_exists( 'Mai_Display_Taxonomy' ) ) {
		return $taxonomies;
	}

	$taxonomies[] = 'mai_display';

	return $taxonomies;
}

add_action( 'simple_page_ordering_ordered_posts', 'maicca_simple_page_ordering_delete_transients', 10, 2 );
/**
 * Delete all transients after simple page reordering.
 *
 * @since N/A
 *
 * @param WP_Post $post    The current post being reordered.
 * @param array   $new_pos The post ID => page attributes values.
 *
 * @return void
 */
function maicca_simple_page_ordering_delete_transients( $post, $new_pos ) {
	if ( ! isset( $post->post_type ) || 'mai_template_part' !== $post->post_type ) {
		return;
	}

	maicca_delete_transients( $post->ID );
}
