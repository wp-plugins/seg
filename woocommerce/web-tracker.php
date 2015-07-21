<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$seg_tracker = new seg_tracker;
add_action('wp_head', array($seg_tracker, 'wp_head'));
add_action('wp_footer', array($seg_tracker, 'wp_footer'));
add_action('woocommerce_order_status_completed', array( $seg_tracker,'send_order'));
add_action('woocommerce_add_to_cart', array( $seg_tracker,'added_to_cart'));
add_action('profile_update', array($seg_tracker, 'action_edit_customer'), 10, 2);

class seg_tracker {
	public $added_to_cart_object = '';

	function send_order($order_id) {
		$order = seg_send_order($order_id);
	}

	function get_product_line($id) {
		$product_object = '';
		$product = new WC_Product($id); 
		$feat_image = wp_get_attachment_url( get_post_thumbnail_id($product->id) );
		
		$product_object = '    "Id": "'.$product->get_sku().'",'."\r\n";
		if($feat_image != ''){
			$product_object .= '    "ImageUrl": "'. stripslashes($feat_image).'",'."\r\n";
		}
		$product_object .= '    "Name": "'.stripslashes($product->post->post_title).'",'."\r\n";
		$product_object .= '    "OriginalPrice": '.$product->get_regular_price().','."\r\n";
		$product_object .= '    "Price": '.$product->get_price().'';

		#get post tags as brands
		$tags = get_the_terms($product->id, 'product_tag' );
		$the_terms = get_the_terms($product->id, 'product_cat');
		$cats = get_all_terms($the_terms, 'product_cat');

		if (!empty($cats) || !empty($tags)) {
			$product_object .= ','."\r\n";
		} else {
			$product_object .= "\r\n";
		}

		if (!empty($cats)) {
			$cats_sep = ',';
		}

		#get brands (we assume that tags are brands at the moment. May have to revert / add in a new collection of tags?)
		#if ($tags) {
		#	$product_object .= '    "Brands": ';
		#	$product_object .= render_tags($tags);
		#	$product_object .='    '.$cats_sep."\r\n";
		#}

		#get categories
		if (!empty($cats)) {
			$product_object .= '    "Categories": ';
			$product_object .= render_tags($cats)."\r\n";
		}

		return $product_object;
	}

	function wp_head() {
		global $current_user;

		if (get_option('seg_guid') != '') {

			#tracking code setup
			echo '<script type="text/javascript">'."\r\n";
			echo ';(function () { var a, b, c, e = window, f = document, g = arguments, h = "script", i = ["config", "track", "identify", "callback"], j = function () { var a, b = this; for (b._s = [], a = 0; a < i.length; a++) !function (a) { b[a] = function () { return b._s.push([a].concat(Array.prototype.slice.call(arguments, 0))), b } }(i[a]) }; for (e._seg = e._seg || {}, a = 0; g.length > a; a++) e._seg[g[a]] = e[g[a]] = e[g[a]] || new j; b = f.createElement(h), b.async = 1, b.src = "https://segapp.blob.core.windows.net/release/seg-analytics.min.js", c = f.getElementsByTagName(h)[0], c.parentNode.insertBefore(b, c) }("seg"));'."\r\n";
			echo "\r\n";
			echo 'seg.config({'."\r\n";
			echo '    site: "'.get_option('seg_guid').'"'."\r\n";
			echo '});'."\r\n";
	
			#identify the user
			if ($current_user->ID != '') {
				$firstName = stripslashes($current_user->billing_first_name);
				$lastName = stripslashes($current_user->billing_last_name);
				$email = stripslashes($current_user->billing_email);
				$countryCode = stripslashes($current_user->billing_country);

				echo 'seg.identify({'."\r\n";
				echo '    "Email" : "'.$email.'"';
				if ($firstName != '') {
					echo ','."\r\n";
    				echo '    "FirstName" : "'.$firstName.'"';
				}
				if ($lastName != '') {
					echo ','."\r\n";
    				echo '    "LastName" : "'.$lastName.'"';
				}
				if ($countryCode != '') {
					echo ','."\r\n";
    				echo '    "CountryCode" : "'.$countryCode.'"';
				}
				echo "\r\n".'});'."\r\n";
			}
	
			echo'</script>';
		}
	}

	function added_to_cart() {
		global $woocommerce;

		$added_to_cart_object = '';
		$cart = $woocommerce->cart->cart_contents;
		$product_id = (int)$_REQUEST["add-to-cart"];
	    $quantity = (int)$_REQUEST["quantity"];

		if ($quantity == '') {
			$quantity = 1;
		}

		foreach ( $cart as $key => $value ) {
			if (is_array($value)) {
				if ($value['product_id'] != $product_id) {
					$cart_products[$value['product_id']] = $value['quantity'];
				} else {
					$quantity = $value['quantity'];
				}
			}
		}

		$sep = '';
		if ($cart_products) {
			if (count($cart_products) > 0) {
				$sep = ',';
			}
		}

		$added_to_cart_object = '{'."\r\n";
		$added_to_cart_object .= '    "OrderLines": ['."\r\n";
	    $added_to_cart_object .= '    {'."\r\n";
	    $added_to_cart_object .= '    "Added": true,'."\r\n";
	    $added_to_cart_object .= '    "Quantity": '.$quantity .','."\r\n";
	    $added_to_cart_object .= $this->get_product_line($product_id);
		$added_to_cart_object .= '}'.$sep;

		if ($cart_products) {
			$cart_products_last = count($cart_products) -1;
			$i = 0;

			foreach ($cart_products as $item_id=>$quantity) {
				if ($cart_products_last != $i) {
					$sep = ', ';
				} else {
					$sep = '';	
				}
				
				$added_to_cart_object .= '{'."\r\n";
				$added_to_cart_object .= '    "Quantity": '.$quantity .','."\r\n";
				$added_to_cart_object .= $this->get_product_line($item_id);
				$added_to_cart_object .= '}'.$sep;
				$i++;	
			}
		}
		
	 	$added_to_cart_object .= "\r\n".']}';

	 	$this->added_to_cart_object = $added_to_cart_object;

	 	#if (get_option('woocommerce_enable_ajax_add_to_cart') === 'yes') {
	 	#}
	}

	function wp_footer() {
		global $current_user, $wp_query, $woocommerce;
		
		if (get_option('seg_guid') != '') {

			echo '<script type="text/javascript">'."\r\n";
	
			if ($this->added_to_cart_object != '') {

				echo 'seg.track("AddedToBasket", ';
				echo $this->added_to_cart_object;
			 	echo ');'."\r\n";

			} elseif (is_product_category()) {

				$cat_obj = $wp_query->get_queried_object();
				$cat = get_term( $cat_obj->term_id, $cat_obj->taxonomy );
				$cats = get_all_terms($cat, 'product_cat');
			
				if ($cats) {
					echo 'seg.track("RangeView", {'."\r\n";
					echo '    "Categories": ';
					echo render_tags($cats)."\r\n";
					echo'});'."\r\n";
				} else {
					echo 'seg.track();'."\r\n";
				}

			#} elseif (is_product_tag()) {

				#$tag_obj = $wp_query->get_queried_object();
				#$tag = get_term( $tag_obj->term_id, $tag_obj->taxonomy );
				#$tags = get_all_terms($tag, 'product_tag');
	
				#if ($tags) {
				#	echo 'seg.track("RangeView", {'."\r\n";
				#	echo '    "Brands": ';
				#	echo render_tags($tags)."\r\n";
				#	echo'});'."\r\n";
				#} else {
				#	echo 'seg.track();'."\r\n";
				#}

			} elseif (is_product()) {
	
  				#$post_details = $wp_query->posts[0];		
				$product_obj = $wp_query->get_queried_object();

				echo 'seg.track("ProductView", {'."\r\n";
				echo $this->get_product_line($product_obj->ID);
				echo '});'."\r\n";	

			} else {
				#Generic page view
				echo 'seg.track();'."\r\n";
			}
	
			echo'</script>';
		}
	}

	function action_edit_customer($id, $data) {
		seg_send_customer($id);
	}
}
