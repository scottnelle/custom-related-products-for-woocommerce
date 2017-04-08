<?php
/*
Plugin Name: Custom Related Products for WooCommerce
Description: Select your own related products instead of pulling them in by category.
Version:     1.3
Plugin URI:  http://scottnelle.com
Author:      Scott Nelle
Author URI:  http://scottnelle.com
*/

/**
 * Force related products to show if some have been selected.
 * This is required for WooCommerce 3.0, which will not display products if
 * There are no categories or tags.
 *
 * @param bool $result Whether or not we should force related posts to display.
 * @param int $product_id The ID of the current product.
 *
 * @return bool Modified value - should we force related products to display?
 */
function crp_force_display( $result, $product_id ) {
	$related_ids = get_post_meta( $product_id, '_related_ids', true );
	return empty( $related_ids ) ? $result : true;
}
add_filter( 'woocommerce_product_related_posts_force_display', 'crp_force_display', 10, 2 );

/**
 * Determine whether we want to consider taxonomy terms when selecting related products.
 * This is required for WooCommerce 3.0.
 *
 * @param bool $result Whether or not we should consider tax terms during selection.
 * @param int $product_id The ID of the current product.
 *
 * @return bool Modified value - should we consider tax terms during selection?
 */
function crp_taxonomy_relation( $result, $product_id ) {
	$related_ids = get_post_meta( $product_id, '_related_ids', true );
	if ( ! empty( $related_ids ) ) {
		return false;
	} else {
		return 'none' === get_option( 'crp_empty_behavior' ) ? false : $result;
	}
}
add_filter( 'woocommerce_product_related_posts_relate_by_category', 'crp_taxonomy_relation', 10, 2 );
add_filter( 'woocommerce_product_related_posts_relate_by_tag', 'crp_taxonomy_relation', 10, 2 );

/**
 * Add related products selector to product edit screen
 */
function crp_select_related_products() {
	global $post, $woocommerce;
	$product_ids = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_related_ids', true ) ) );
	?>
	<div class="options_group">
		<?php if ( $woocommerce->version >= '3.0' ) : ?>
			<p class="form-field">
				<label for="related_ids"><?php _e( 'Related Products', 'woocommerce' ); ?></label>
				<select class="wc-product-search" multiple="multiple" style="width: 50%;" id="related_ids" name="related_ids[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-exclude="<?php echo intval( $post->ID ); ?>">
					<?php
						foreach ( $product_ids as $product_id ) {
							$product = wc_get_product( $product_id );
							if ( is_object( $product ) ) {
								echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
							}
						}
					?>
				</select> <?php echo wc_help_tip( __( 'Related products are displayed on the product detail page.', 'woocommerce' ) ); ?>
			</p>
		<?php elseif ( $woocommerce->version >= '2.3' ) : ?>
			<p class="form-field"><label for="related_ids"><?php _e( 'Related Products', 'woocommerce' ); ?></label>
				<input type="hidden" class="wc-product-search" style="width: 50%;" id="related_ids" name="related_ids" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products" data-multiple="true" data-selected="<?php
					$json_ids = array();
					foreach ( $product_ids as $product_id ) {
						$product = wc_get_product( $product_id );
						if ( is_object( $product ) && is_callable( array( $product, 'get_formatted_name' ) ) ) {
							$json_ids[ $product_id ] = wp_kses_post( $product->get_formatted_name() );
						}
					}

					echo esc_attr( json_encode( $json_ids ) );
				?>" value="<?php echo implode( ',', array_keys( $json_ids ) ); ?>" /> <img class="help_tip" data-tip='<?php _e( 'Related products are displayed on the product detail page.', 'woocommerce' ) ?>' src="<?php echo WC()->plugin_url(); ?>/assets/images/help.png" height="16" width="16" />
			</p>
		<?php else: ?>
			<p class="form-field"><label for="related_ids"><?php _e( 'Related Products', 'woocommerce' ); ?></label>
				<select id="related_ids" name="related_ids[]" class="ajax_chosen_select_products" multiple="multiple" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>">
					<?php
						foreach ( $product_ids as $product_id ) {

							$product = get_product( $product_id );

							if ( $product )
								echo '<option value="' . esc_attr( $product_id ) . '" selected="selected">' . esc_html( $product->get_formatted_name() ) . '</option>';
						}
					?>
				</select> <img class="help_tip" data-tip='<?php _e( 'Related products are displayed on the product detail page.', 'woocommerce' ) ?>' src="<?php echo WC()->plugin_url(); ?>/assets/images/help.png" height="16" width="16" />
			</p>
		<?php endif; ?>
	</div>
	<?php
}
add_action('woocommerce_product_options_related', 'crp_select_related_products');

/**
 * Save related products selector on product edit screen.
 *
 * @param int $post_id ID of the post to save.
 * @param obj WP_Post object.
 */
function crp_save_related_products( $post_id, $post ) {
	global $woocommerce;
	if ( isset( $_POST['related_ids'] ) ) {
		// From 2.3 until the release before 3.0 Woocommerce posted these as a comma-separated string.
		// Before and after, they are posted as an array of IDs.
		if ( $woocommerce->version >= '2.3' && $woocommerce->version < '3.0' ) {
		$related = isset( $_POST['related_ids'] ) ? array_filter( array_map( 'intval', explode( ',', $_POST['related_ids'] ) ) ) : array();
		} else {
			$related = array();
			$ids = $_POST['related_ids'];
			foreach ( $ids as $id ) {
				if ( $id && $id > 0 ) { $related[] = absint( $id ); }
			}
		}
		update_post_meta( $post_id, '_related_ids', $related );
	} else {
		delete_post_meta( $post_id, '_related_ids' );
	}
}
add_action( 'woocommerce_process_product_meta', 'crp_save_related_products', 10, 2 );

/**
 * Filter the related product query args.
 * This function works for WooCommerce prior to 3.0.
 *
 * @param array $args Query arguments.
 *
 * @return array Modified query arguments.
 */
function crp_filter_related_products_legacy( $args ) {
	global $post;
	$related = get_post_meta( $post->ID, '_related_ids', true );
	if ($related) { // remove category based filtering
		$args['post__in'] = $related;
	}
	elseif (get_option( 'crp_empty_behavior' ) == 'none') { // don't show any products
		$args['post__in'] = array(0);
	}

	return $args;
}
add_filter( 'woocommerce_related_products_args', 'crp_filter_related_products_legacy' );

/**
 * Filter the related product query args.
 *
 * @param array $query Query arguments.
 * @param int $product_id The ID of the current product.
 *
 * @return array Modified query arguments.
 */
function crp_filter_related_products( $query, $product_id ) {
	$related_ids = get_post_meta( $product_id, '_related_ids', true );
	if ( ! empty( $related_ids ) && is_array( $related_ids ) ) {
		$related_ids = implode( ',', array_map( 'absint', $related_ids ) );
		$query['where'] .= " AND p.ID IN ( {$related_ids} )";
	}
	return $query;
}
add_filter( 'woocommerce_product_related_posts_query', 'crp_filter_related_products', 20, 2 );


/**
 * Create the menu item.
 */
function crp_create_menu() {
	add_submenu_page( 'woocommerce', 'Custom Related Products', 'Custom Related Products', 'manage_options', 'custom_related_products', 'crp_settings_page');
}
add_action('admin_menu', 'crp_create_menu', 99);

/**
 * Create the settings page.
 */
function crp_settings_page() {
	if ( isset($_POST['submit_custom_related_products']) && current_user_can('manage_options') ) {
		check_admin_referer( 'custom_related_products', '_custom_related_products_nonce' );

		// save settings
		if (isset($_POST['crp_empty_behavior']) && $_POST['crp_empty_behavior'] != '') {
			update_option( 'crp_empty_behavior', $_POST['crp_empty_behavior'] );
		}
		else {
			delete_option( 'crp_empty_behavior' );
		}

		echo '<div id="message" class="updated"><p>Settings saved</p></div>';
	}

	?>
	<div class="wrap" id="custom-related-products">
		<h2>Custom Related Products</h2>
	<?php
	$behavior_none_selected = (get_option( 'crp_empty_behavior' ) == 'none') ? 'selected="selected"' : '';

	echo '
		<form method="post" action="admin.php?page=custom_related_products">
			'.wp_nonce_field( 'custom_related_products', '_custom_related_products_nonce', true, false ).'
			<p>If I have not selected related products:
				<select name="crp_empty_behavior">
					<option value="">Select random related products by category</option>
					<option value="none" '.$behavior_none_selected.'>Don&rsquo;t show any related products</option>
				</select>
			</p>
			<p>
				<input type="submit" name="submit_custom_related_products" value="Save" class="button button-primary" />
			</p>
		</form>
	';
	?>
	</div>

	<?php
} // end settings page
