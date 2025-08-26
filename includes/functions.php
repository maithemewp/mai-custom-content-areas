<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Displays a content area.
 *
 * @since 0.1.0
 *
 * @param string $type The cca type. Accepts 'single' or 'archive'.
 * @param array  $args The content area args.
 *
 * @return void
 */
function maicca_do_cca( $type, $args ) {
	switch ( $type ) {
		case 'global':
			maicca_do_global_cca( $args );
		break;
		case 'single':
			maicca_do_single_cca( $args );
		break;
		case 'archive':
			maicca_do_archive_cca( $args );
		break;
	}
}

/**
 * Displays a global content area.
 *
 * @since TBD
 *
 * @param array $args The content area args.
 *
 * @return void
 */
function maicca_do_global_cca( $args ) {
	$args = wp_parse_args( $args,
		[
			'id'       => '',
			'location' => '',
			'content'  => '',
		]
	);

	// Sanitize.
	$args = [
		'id'       => absint( $args['id'] ),
		'location' => esc_html( $args['location'] ),
		'content'  => trim( $args['content'] ),
	];

	// Late filter to hide CCA.
	// This filter only runs if the CCA is going to display.
	// Also see `maicca_hide_cca` filter for earlier check.
	$show = (bool) apply_filters( 'maicca_show_cca', true, $args );

	if ( ! $show ) {
		return;
	}

 	// Add displayed CCA to array for later.
	maicca_get_page_ccas( $args );

	// Run action hook.
	do_action( 'maicca_cca', $args );

	// Get locations and priority.
	$locations = maicca_get_locations();
	$priority  = isset( $locations[ $args['location'] ]['priority'] ) && $locations[ $args['location'] ]['priority'] ? $locations[ $args['location'] ]['priority'] : 10;

	// Run action hook.
	add_action( $locations[ $args['location'] ]['hook'], function() use ( $args, $priority ) {
		// Allow filtering of content just before display.
		$args['content'] = maicca_get_processed_content( $args['content'] );
		$args['content'] = apply_filters( 'maicca_content', $args['content'], $args );

		echo $args['content'];

	}, $priority );
}

/**
 * Displays a content area on singular.
 *
 * @since 0.1.0
 *
 * @param array $args The content area args.
 *
 * @return void
 */
function maicca_do_single_cca( $args ) {
	if ( ! maicca_is_singular() ) {
		return;
	}

	$args = wp_parse_args( $args,
		[
			'id'                  => '',
			'location'            => '',
			'content'             => '',
			'content_location'    => 'after',
			'content_count'       => 6,
			'types'               => [],
			'keywords'            => '',
			'taxonomies'          => [],
			'taxonomies_relation' => 'AND',
			'authors'             => [],
			'include'             => [],
			'exclude'             => [],
			// 'includes'            => [],
		]
	);

	// Sanitize.
	$args = [
		'id'                  => absint( $args['id'] ),
		'location'            => esc_html( $args['location'] ),
		'content'             => trim( $args['content'] ),
		'content_location'    => esc_html( $args['content_location'] ),
		'content_count'       => absint( $args['content_count'] ),
		'types'               => $args['types'] ? array_map( 'esc_html', (array) $args['types'] ) : [],
		'keywords'            => maicca_sanitize_keywords( $args['keywords'] ),
		'taxonomies'          => maicca_sanitize_taxonomies( $args['taxonomies'] ),
		'taxonomies_relation' => esc_html( $args['taxonomies_relation'] ),
		'authors'             => $args['authors'] ? array_map( 'absint', (array) $args['authors'] ) : [],
		'include'             => $args['include'] ? array_map( 'absint', (array) $args['include'] ) : [],
		'exclude'             => $args['exclude'] ? array_map( 'absint', (array) $args['exclude'] ) : [],
		// 'includes'            => $args['includes'] ? array_map( 'sanitize_key', (array) $args['includes'] ) : [],
	];

	// Bail if user can't view.
	if ( ! maicca_can_view( $args ) ) {
		return;
	}

	// Set variables.
	$post_id   = get_the_ID();
	$post_type = get_post_type();
	$locations = maicca_get_locations();

	// Bail if excluding this entry.
	if ( $args['exclude'] && in_array( $post_id, $args['exclude'] ) ) {
		return;
	}

	// If including this entry.
	$include = $args['include'] && in_array( $post_id, $args['include'] );

	// If not already including, check post types.
	// Using '*' is not currently an option. This is here for future use.
	if ( ! $include && ! ( in_array( '*', $args['types'] ) || in_array( $post_type, $args['types'] ) ) ) {
		return;
	}

	// If not already including, and have keywords, check for them.
	if ( ! $include && $args['keywords'] ) {
		$post         = get_post( $post_id );
		$post_content = maicca_strtolower( strip_tags( do_shortcode( trim( $post->post_content ) ) ) );

		if ( ! ( function_exists( 'mai_has_string' ) && mai_has_string( $args['keywords'], $post_content ) ) ) {
			return;
		}
	}

	// If not already including, check taxonomies.
	if ( ! $include && $args['taxonomies'] ) {

		if ( 'AND' === $args['taxonomies_relation'] ) {

			// Loop through all taxonomies to give a chance to bail if NOT IN.
			foreach ( $args['taxonomies'] as $data ) {
				$has_term = has_term( $data['terms'], $data['taxonomy'] );

				// Bail if we have a term and we aren't displaying here.
				if ( $has_term && 'NOT IN' === $data['operator'] ) {
					return;
				}

				// Bail if we have don't a term and we are dislaying here.
				if ( ! $has_term && 'IN' === $data['operator'] ) {
					return;
				}
			}

		} elseif ( 'OR' === $args['taxonomies_relation'] ) {

			$meets_any = false;

			foreach ( $args['taxonomies'] as $data ) {
				$has_term = has_term( $data['terms'], $data['taxonomy'] );

				if ( $has_term && 'IN' === $data['operator'] ) {
					$meets_any = true;
					break;
				}

				if ( ! $has_term && 'NOT IN' === $data['operator'] ) {
					$meets_any = true;
					break;
				}
			}

			if ( ! $meets_any ) {
				return;
			}
		}
	}

	// If not already including, check authors.
	if ( ! $include && $args['authors'] ) {
		$author_id = get_post_field( 'post_author', $post_id );

		if ( ! in_array( $author_id, $args['authors'] ) ) {
			return;
		}
	}

	// Late filter to hide CCA.
	// This filter only runs if the CCA is going to display.
	// Also see `maicca_hide_cca` filter for earlier check.
	$show = (bool) apply_filters( 'maicca_show_cca', true, $args );

	if ( ! $show ) {
		return;
	}

 	// Add displayed CCA to array for later.
	maicca_get_page_ccas( $args );

	// Run action hook.
	do_action( 'maicca_cca', $args );

	if ( 'content' === $args['location'] ) {

		add_filter( 'the_content', function( $content ) use ( $args ) {
			if ( ! ( is_main_query() && in_the_loop() ) ) {
				return $content;
			}

			// Allow filtering of content just before display.
			$args['content'] = maicca_get_processed_content( $args['content'] );
			$args['content'] = apply_filters( 'maicca_content', $args['content'], $args );

			return maicca_add_cca( $content, $args['content'],
				[
					'location' => $args['content_location'],
					'count'    => $args['content_count'],
				]
			);
		});

	} else {

		// Get priority.
		$priority = isset( $locations[ $args['location'] ]['priority'] ) && $locations[ $args['location'] ]['priority'] ? $locations[ $args['location'] ]['priority'] : 10;

		// Run action hook.
		add_action( $locations[ $args['location'] ]['hook'], function() use ( $args, $priority ) {

			// Allow filtering of content just before display.
			$args['content'] = maicca_get_processed_content( $args['content'] );
			$args['content'] = apply_filters( 'maicca_content', $args['content'], $args );

			echo $args['content'];

		}, $priority );
	}
}

/**
 * Displays a content area on archives.
 *
 * @since 0.1.0
 *
 * @param array $args The content area args.
 *
 * @return void
 */
function maicca_do_archive_cca( $args ) {
	if ( ! maicca_is_archive() ) {
		return;
	}

	$args = wp_parse_args( $args,
		[
			'id'            => '',
			'location'      => '',
			'content'       => '',
			'content_count' => 3,
			'types'         => [],
			'taxonomies'    => [],
			'terms'         => [],
			'exclude'       => [],
			'paged'         => 0,
			'includes'      => [],
		]
	);

	// Sanitize.
	$args = [
		'id'            => absint( $args['id'] ),
		'location'      => esc_html( $args['location'] ),
		'content'       => trim( $args['content'] ),
		'content_count' => absint( $args['content_count'] ),
		'types'         => $args['types'] ? array_map( 'esc_html', (array) $args['types'] ) : [],
		'taxonomies'    => $args['taxonomies'] ? array_map( 'esc_html', (array) $args['taxonomies'] ) : [],
		'terms'         => $args['terms'] ? array_map( 'absint', (array) $args['terms'] ) : [],
		'exclude'       => $args['exclude'] ? array_map( 'absint', (array) $args['exclude'] ) : [],
		'paged'         => absint( $args['paged'] ),
		'includes'      => $args['includes'] ? array_map( 'sanitize_key', (array) $args['includes'] ) : [],
	];

	// Bail if user can't view.
	if ( ! maicca_can_view( $args ) ) {
		return;
	}

	// Set variables.
	$locations = maicca_get_locations();

	// Blog.
	if ( is_home() ) {
		// Bail if not showing on post archive.
		// Using '*' is not currently an option. This is here for future use.
		if ( ! $args['types'] || ! ( in_array( '*', $args['types'] ) || in_array( 'post', $args['types'] ) ) ) {
			return;
		}
	}
	// CPT archive. WooCommerce shop returns false for `is_post_type_archive()`.
	elseif ( is_post_type_archive() || maicca_is_shop_archive() ) {
		// Bail if shop page and not showing here.
		// Using '*' is not currently an option. This is here for future use.
		if ( maicca_is_shop_archive() ) {
			if ( ! $args['types'] || ! ( in_array( '*', $args['types'] ) || in_array( 'product', $args['types'] ) ) ) {
				return;
			}
		}
		// Bail if not showing on this post type archive.
		else {
			global $wp_query;

			$post_type = isset( $wp_query->query['post_type'] ) ? $wp_query->query['post_type'] : '';

			if ( ! $args['types'] || ! ( in_array( '*', $args['types'] ) || ( $post_type && in_array( $post_type, $args['types'] ) ) ) ) {
				return;
			}
		}
	}
	// Term archive.
	elseif ( is_tax() || is_category() || is_tag() ) {
		$object = get_queried_object();

		// Bail if excluding this term archive.
		if ( $args['exclude'] && in_array( $object->term_id, $args['exclude'] ) ) {
			return;
		}

		// If including this entry.
		$include = $args['terms'] && in_array( $object->term_id, $args['terms'] );

		// If not already including, check taxonomies if we're restricting to specific taxonomies.
		if ( ! $include && ! ( $args['taxonomies'] && in_array( $object->taxonomy, $args['taxonomies'] ) ) ) {
			return;
		}
	}
	// Search results;
	elseif ( is_search() ) {
		// Bail if not set to show on search results.
		if ( ! ( $args['includes'] || in_array( 'search', $args['includes'] ) ) ) {
			return;
		}
	}

	// Pagination.
	if ( is_paged() && $args['paged'] ) {
		// Bail if not set to show on this page.
		if ( get_query_var( 'paged' ) > $args['paged'] ) {
			return;
		}
	}

	// Add displayed CCA to array for later.
	maicca_get_page_ccas( $args );

	// Run action hook.
	do_action( 'maicca_cca', $args );

	// Get priority.
	$priority = isset( $locations[ $args['location'] ]['priority'] ) && $locations[ $args['location'] ]['priority'] ? $locations[ $args['location'] ]['priority'] : 10;

	if ( 'entries' === $args['location'] ) {
		// Show CSS in the head.
		add_action( 'wp_head', 'maicca_do_archive_css' );
		// Add attributes to entries-wrap.
		add_filter( 'genesis_attr_entries-wrap', 'maicca_entries_wrap_atts', 10, 3 );

		/**
		 * Adds inline CSS and CCA markup before the closing entries-wrap element.
		 *
		 * @since 1.1.0
		 *
		 * @param string $close       The closing element.
		 * @param array  $markup_args The args Mai passes to the element.
		 *
		 * @return string
		 */
		add_filter( 'genesis_markup_entries-wrap_close', function( $close, $markup_args ) use ( $args ) {
			if ( ! $close ) {
				return $close;
			}

			if ( ! isset( $markup_args['params']['args']['context'] ) || 'archive' !== $markup_args['params']['args']['context'] ) {
				return $close;
			}

			// Allow filtering of content just before display.
			$args['content'] = maicca_get_processed_content( $args['content'] );
			$args['content'] = apply_filters( 'maicca_content', $args['content'], $args );

			$count = $args['content_count'];
			$cca   = sprintf( '<div class="mai-cca" style="order:calc(var(--maicca-columns) * %s);">%s</div>', $count, $args['content'] );

			return $cca . $close;

		}, 10, 2 );

	} else {
		// Run action hook.
		add_action( $locations[ $args['location'] ]['hook'], function() use ( $args, $priority ) {

			// Allow filtering of content just before display.
			$args['content'] = maicca_get_processed_content( $args['content'] );
			$args['content'] = apply_filters( 'maicca_content', $args['content'], $args );

			echo $args['content'];

		}, $priority );
	}
}

/**
 * Gets content areas by type.
 *
 * @since 0.1.0
 *
 * @param string $type
 * @param bool   $use_cache
 *
 * @return array
 */
function maicca_get_ccas( $use_cache = true ) {
	if ( ! ( function_exists( 'get_field' ) && function_exists( 'mai_get_template_part_ids' ) ) ) {
		return [];
	}

	static $ccas = null;

	if ( $ccas && $use_cache ) {
		return $ccas;
	}

	if ( ! is_array( $ccas ) ) {
		$ccas = [];
	}

	$transient = 'mai_ccas';

	if ( ! $use_cache || ( false === ( $queried_ccas = get_transient( $transient ) ) ) ) {

		$queried_ccas = [];
		$query        = new WP_Query(
			[
				'post_type'              => 'mai_template_part',
				'post_status'            => [ 'publish', 'private' ],
				'posts_per_page'         => 500,
				'post__not_in'           => array_values( mai_get_template_part_ids() ),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => false, // https://github.com/10up/Engineering-Best-Practices/issues/116
				'orderby'                => 'menu_order',
				'order'                  => 'ASC',
			]
		);

		if ( $query->have_posts() ) {

			while ( $query->have_posts() ) : $query->the_post();

				$post_id          = get_the_ID();
				$post_status      = get_post_status();
				$content          = get_post()->post_content;
				$global_location  = get_field( 'maicca_global_location' );
				$single_location  = get_field( 'maicca_single_location' );
				$archive_location = get_field( 'maicca_archive_location' );

				if ( $global_location ) {
					$global_data = [
						'id'       => $post_id,
						'status'   => $post_status,
						'location' => $global_location,
						'content'  => $content,
					];

					$queried_ccas['global'][] = maicca_filter_associative_array( $global_data );
				}

				if ( $single_location ) {
					$single_data = [
						'id'                  => $post_id,
						'status'              => $post_status,
						'location'            => $single_location,
						'content'             => $content,
						'content_location'    => get_field( 'maicca_single_content_location' ),
						'content_count'       => get_field( 'maicca_single_content_count' ),
						'types'               => get_field( 'maicca_single_types' ),
						'keywords'            => get_field( 'maicca_single_keywords' ),
						'taxonomies'          => get_field( 'maicca_single_taxonomies' ),
						'taxonomies_relation' => get_field( 'maicca_single_taxonomies_relation' ),
						'authors'             => get_field( 'maicca_single_authors' ),
						'include'             => get_field( 'maicca_single_entries' ),
						'exclude'             => get_field( 'maicca_single_exclude_entries' ),
						// 'includes'            => get_field( 'maicca_single_includes' ),
					];

					$queried_ccas['single'][] = maicca_filter_associative_array( $single_data );
				}

				if ( $archive_location ) {

					$archive_data = [
						'id'               => $post_id,
						'status'           => $post_status,
						'location'         => $archive_location,
						'content'          => $content,
						'content_count'    => get_field( 'maicca_archive_content_count' ),
						'types'            => get_field( 'maicca_archive_types' ),
						'taxonomies'       => get_field( 'maicca_archive_taxonomies' ),
						'terms'            => get_field( 'maicca_archive_terms' ),
						'exclude'          => get_field( 'maicca_archive_exclude_terms' ),
						'paged'            => get_field( 'maicca_archive_paged' ),
						'includes'         => get_field( 'maicca_archive_includes' ),
					];

					$queried_ccas['archive'][] = maicca_filter_associative_array( $archive_data );
				}

			endwhile;
		}

		wp_reset_postdata();

		// Set transient, and expire after 1 hour.
		set_transient( $transient, $queried_ccas, 1 * HOUR_IN_SECONDS );
	}

	$ccas = $queried_ccas;

	return $ccas;
}

/**
 * Get content area hook locations.
 *
 * @since 0.1.0
 *
 * @return array
 */
function maicca_get_locations() {
	static $locations = null;

	if ( ! is_null( $locations ) ) {
		return $locations;
	}

	$locations = [
		'before_header'        => [
			'hook'     => 'genesis_before_header',
			'priority' => 5, // Before header default content area is 10.
		],
		'after_header'        => [
			'hook'     => 'genesis_after_header',
			'priority' => 15,
		],
		'before_loop'         => [
			'hook'     => 'genesis_loop',
			'priority' => 5,
		],
		'before_entry'         => [
			'hook'     => 'genesis_before_entry',
			'priority' => 10,
		],
		'before_entry_content' => [
			'hook'     => 'genesis_before_entry_content',
			'priority' => 10,
		],
		'content'              => [
			'hook'     => '', // No hooks, counted in content.
			'priority' => null,
		],
		'entries'              => [
			'hook'     => '', // No hooks, handled in function.
			'priority' => 10,
		],
		'after_entry_content'  => [
			'hook'     => 'genesis_after_entry_content',
			'priority' => 10,
		],
		'after_entry'          => [
			'hook'     => 'genesis_after_entry',
			'priority' => 8, // Comments are at 10.
		],
		'after_loop'           => [
			'hook'     => 'genesis_loop',
			'priority' => 15,
		],
		'before_footer'        => [
			'hook'     => 'genesis_after_content_sidebar_wrap',
			'priority' => 10,
		],
		'after_footer' => [
			'hook'     => 'wp_footer',
			'priority' => 20,
		],
	];

	if ( maicca_is_product_archive() || maicca_is_product_singular() ) {
		$locations['before_loop'] = [
			'hook'     => 'woocommerce_before_shop_loop',
			'priority' => 12, // Notices are at 10.
		];

		$locations['before_entry']         = [
			'hook'     => 'woocommerce_before_single_product',
			'priority' => 12, // Notices are at 10.
		];

		$locations['before_entry_content'] = [
			'hook'     => 'woocommerce_after_single_product_summary',
			'priority' => 8, // Tabs are at 10.
		];

		$locations['after_entry_content']  = [
			'hook'     => 'woocommerce_after_single_product_summary',
			'priority' => 12, // Tabs are at 10, upsells and related products are 15.
		];

		$locations['after_entry']          = [
			'hook'     => 'woocommerce_after_single_product',
			'priority' => 10,
		];

		$locations['after_loop']           = [
			'hook'     => 'woocommerce_after_shop_loop',
			'priority' => 12, // Pagination is at 10.
		];
	}

	$locations = apply_filters( 'maicca_locations', $locations );

	if ( $locations ) {
		foreach ( $locations as $name => $location ) {
			$locations[ $name ] = wp_parse_args( (array) $location,
				[
					'hook'     => '',
					'priority' => null,
				]
			);
		}
	}

	return $locations;
}

/**
 * Displays archive CSS.
 *
 * @since 1.3.0
 *
 * @return void
 */
function maicca_do_archive_css() {
	static $has_css = false;

	if ( $has_css ) {
		return;
	}

	?>
	<style>
		.mai-cca {
			flex:1 1 100%;
		}
		@media only screen and (max-width: 599px) {
			.entries-wrap {
				--maicca-columns: var(--maicca-columns-xs);
			}
		}
		@media only screen and (min-width: 600px) and (max-width: 799px) {
			.entries-wrap {
				--maicca-columns: var(--maicca-columns-sm);
			}
		}
		@media only screen and (min-width: 800px) and (max-width: 999px) {
			.entries-wrap {
				--maicca-columns: var(--maicca-columns-md);
			}
		}
		@media only screen and (min-width: 1000px) {
			.entries-wrap {
				--maicca-columns: var(--maicca-columns-lg);
			}
		}
	</style>
	<?php
	$has_css = true;
}

/**
 * Adds custom properties for the column count as an integer.
 * Mai Engine v2.22.0 changed --columns from integer to fraction, which broke Mai CCAs.
 *
 * @since 1.3.0
 *
 * @param array  $atts        The existing element attributes.
 * @param string $context     The element context.
 * @param array  $markup_args The args Mai passes to the element.
 *
 * @return array
 */
function maicca_entries_wrap_atts( $atts, $context, $markup_args ) {
	if ( ! isset( $markup_args['params']['args']['context'] ) || 'archive' !== $markup_args['params']['args']['context'] ) {
		return $atts;
	}

	if ( ! function_exists( 'mai_get_breakpoint_columns' ) ) {
		return $atts;
	}

	// Static variable since these filters would run for each CCA.
	static $has_atts = false;

	if ( $has_atts ) {
		return $atts;
	}

	$atts['style'] = isset( $atts['style'] ) ? $atts['style'] : '';
	$columns       = array_reverse( mai_get_breakpoint_columns( $markup_args['params']['args'] ) );

	foreach ( $columns as $break => $column ) {
		$atts['style'] .= sprintf( '--maicca-columns-%s:%s;', $break, $column );
	}

	$has_atts = true;

	return $atts;
}

/**
 * Gets all CCAs displayed on the page.
 * Optionally add a CCA to the displayed CCAs array.
 *
 * @access private
 *
 * @since Unknown
 *
 * @param string $ad
 *
 * @return array
 */
function maicca_get_page_ccas( $cca = '' ) {
	static $cache = [];

	if ( $cca ) {
		$cache[] = $cca;
	}

	return $cache;
}
