<?php
/*
Plugin Name: Custom Related Products for WooCommerce
Description: Select your own related products instead of pulling them in by category.
Version:     1.1
Plugin URI:  http://scottnelle.com
Author:      Scott Nelle
Author URI:  http://scottnelle.com
*/

// add related products selector to product edit screen
function crp_select_related_products() {
	global $post, $woocommerce;
	$product_ids = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_related_ids', true ) ) );
	?>
	<div class="options_group">
		<?php if ( $woocommerce->version >= '2.3' ) : ?> 
			<p class="form-field"><label for="related_ids"><?php _e( 'Related Products', 'woocommerce' ); ?></label>
				<input type="hidden" class="wc-product-search" style="width: 50%;" id="related_ids" name="related_ids" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products" data-multiple="true" data-selected="<?php
					$json_ids = array();
					foreach ( $product_ids as $product_id ) {
						$product = wc_get_product( $product_id );
						$json_ids[ $product_id ] = wp_kses_post( $product->get_formatted_name() );
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

// save related products selector on product edit screen
function crp_save_related_products( $post_id, $post ) {
	global $woocommerce;
	if ( isset( $_POST['related_ids'] ) ) {
		if ( $woocommerce->version >= '2.3' ) {
		$related = isset( $_POST['related_ids'] ) ? array_filter( array_map( 'intval', explode( ',', $_POST['related_ids'] ) ) ) : array();
		} else {
			$related = array();
			$ids = $_POST['related_ids'];
			foreach ( $ids as $id ) {
				if ( $id && $id > 0 ) { $related[] = $id; }
			}
		}
		update_post_meta( $post_id, '_related_ids', $related );
	} else {
		delete_post_meta( $post_id, '_related_ids' );
	}
}
add_action( 'woocommerce_process_product_meta', 'crp_save_related_products', 10, 2 );

// filter the arguments of the related products query to match those selected, if any
function crp_filter_related_products($args) {
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
add_filter( 'woocommerce_related_products_args', 'crp_filter_related_products' );

// create the menu item
function crp_create_menu() {
	add_submenu_page( 'woocommerce', 'Custom Related Products', 'Custom Related Products', 'manage_options', 'custom_related_products', 'crp_settings_page');
}
add_action('admin_menu', 'crp_create_menu', 99);

// create the settings page
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

		<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
			<input type="hidden" name="cmd" value="_s-xclick">
			<input type="hidden" name="hosted_button_id" value="TNF7SKVWY3AMY">
			<p>Love this plugin? Feeling generous? <br />
				<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			</p>
			<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
		</form>
	</div>

	<?php
} // end settings page