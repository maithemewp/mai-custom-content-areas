<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

add_action( 'admin_enqueue_scripts', 'maicca_enqueue_admin_scripts' );
/**
 * Enqueue admin JS file to dynamically change post type value
 * in include/exclude post object query.
 *
 * @since 0.1.0
 *
 * @param string $hook The current screen hook.
 *
 * @return void
 */
function maicca_enqueue_admin_scripts( $hook ) {
	if ( ! in_array( $hook, [ 'post-new.php', 'post.php' ] ) ) {
		return;
	}

	if ( 'mai_template_part' !== get_post_type() ) {
		return;
	}

	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_enqueue_script( 'mai-cca', MAI_CCA_PLUGIN_URL . "assets/js/mai-cca{$suffix}.js", [ 'jquery' ], MAI_CCA_VERSION, true );
}

add_action( 'acf/render_field/key=maicca_single_tab', 'maicca_admin_css' );
/**
 * Adds custom CSS in the first field.
 *
 * @since 0.1.0
 *
 * @return void
 */
function maicca_admin_css( $field ) {
	echo '<style>
	#acf-maicca_field_group > .acf-fields {
		padding-bottom: 5vh !important;
	}
	.acf-field-maicca-single-taxonomies .acf-repeater .acf-actions {
		text-align: start;
	}
	.acf-field-maicca-single-taxonomies .acf-repeater .acf-button {
		float: none;
	}
	</style>';
}

add_filter( 'acf/load_field/key=maicca_single_types', 'maicca_load_content_types' );
/**
 * Loads singular content types.
 *
 * @since 0.1.0
 *
 * @param array $field The field data.
 *
 * @return array
 */
function maicca_load_content_types( $field ) {
	if ( ! maicca_is_editor() ) {
		return $field;
	}

	$field['choices'] = maicca_get_post_type_choices();

	return $field;
}

add_filter( 'acf/load_field/key=maicca_single_taxonomy', 'maicca_load_single_taxonomy' );
/**
 * Loads display terms as choices.
 *
 * @since 0.1.0
 *
 * @param array $field The field data.
 *
 * @return array
 */
function maicca_load_single_taxonomy( $field ) {
	if ( ! maicca_is_editor() ) {
		return $field;
	}

	$field['choices'] = maicca_get_taxonomy_choices();

	return $field;
}

add_filter( 'acf/load_field/key=maicca_single_terms', 'maicca_acf_load_single_terms', 10, 1 );
/**
 * Get terms from an ajax query.
 * The taxonomy is passed via JS on select2_query_args filter.
 *
 * @since 0.1.0
 *
 * @param array $field The ACF field array.
 *
 * @return mixed
 */
function maicca_acf_load_single_terms( $field ) {
	if ( ! maicca_is_editor() ) {
		return $field;
	}

	if ( function_exists( 'mai_acf_load_terms' ) ) {
		$field = mai_acf_load_terms( $field );
	}

	return $field;
}

add_filter( 'acf/prepare_field/key=maicca_single_terms', 'maicca_acf_prepare_single_terms', 10, 1 );
/**
 * Get terms from an ajax query.
 * The taxonomy is passed via JS on select2_query_args filter.
 *
 * @since 0.1.0
 *
 * @param array $field The ACF field array.
 *
 * @return mixed
 */
function maicca_acf_prepare_single_terms( $field ) {
	if ( ! maicca_is_editor() ) {
		return $field;
	}

	if ( function_exists( 'mai_acf_prepare_terms' ) ) {
		$field = mai_acf_prepare_terms( $field );
	}

	return $field;
}

add_filter( 'acf/load_field/key=maicca_archive_types', 'maicca_acf_load_archive_post_types', 10, 1 );
/**
 * Gets post type archive choices.
 * @since 0.1.0
 *
 * @param array $field The ACF field array.
 *
 * @return mixed
 */
function maicca_acf_load_archive_post_types( $field ) {
	if ( ! maicca_is_editor() ) {
		return $field;
	}

	$post_types = maicca_get_post_type_choices();

	foreach ( $post_types as $name => $label ) {
		$object = get_post_type_object( $name );

		if ( 'post' === $name || $object->has_archive ) {
			continue;
		}

		unset( $post_types[ $name ] );
	}

	$field['choices'] = $post_types;

	return $field;
}

add_filter( 'acf/load_field/key=maicca_archive_taxonomies', 'maicca_acf_load_all_taxonomies', 10, 1 );
/**
 * Gets taxonomy archive choices.
 *
 * @since 0.1.0
 *
 * @param array $field The ACF field array.
 *
 * @return mixed
 */
function maicca_acf_load_all_taxonomies( $field ) {
	if ( ! maicca_is_editor() ) {
		return $field;
	}

	$field['choices'] = maicca_get_taxonomy_choices();

	return $field;
}

add_filter( 'acf/load_field/key=maicca_archive_terms',         'maicca_acf_load_all_terms', 10, 1 );
add_filter( 'acf/load_field/key=maicca_archive_exclude_terms', 'maicca_acf_load_all_terms', 10, 1 );
/**
 * Gets term choices.
 *
 * @since 0.1.0
 *
 * @param array $field The ACF field array.
 *
 * @return mixed
 */
function maicca_acf_load_all_terms( $field ) {
	if ( ! maicca_is_editor() ) {
		return $field;
	}

	$field['choices'] = [];
	$taxonomies       = maicca_get_taxonomies();

	foreach( $taxonomies as $taxonomy ) {
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'id=>name',
			]
		);

		if ( ! $terms ) {
			continue;
		}

		$optgroup                      = sprintf( '%s (%s)', get_taxonomy( $taxonomy )->label, $taxonomy );
		$field['choices'][ $optgroup ] = $terms;
	}

	return $field;
}

add_action( 'acf/save_post', 'maicca_delete_transients', 99 );
/**
 * Clears the transients on post type save/update.
 *
 * @since 0.2.1
 *
 * @return void
 */
function maicca_delete_transients( $post_id ) {
	if ( 'mai_template_part' !== get_post_type( $post_id ) ) {
		return;
	}

	delete_transient( 'mai_ccas' );
	$prime_cache = maicca_get_ccas( false );
}
