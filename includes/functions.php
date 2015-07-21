<?php

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

function get_all_terms($the_terms, $taxonomy) {
	try {

		$terms = [];

		if (count($the_terms) > 0) {
			if (!is_array($the_terms)){
			   $the_terms = array($the_terms);
			}

			foreach ($the_terms as $key=>$term) {
		        $ancestors = get_ancestors( $term->term_id, $taxonomy );
		        if ( ! empty( $ancestors ) ) {
		        	array_push($terms, $term);

		            $ancestors = array_reverse( $ancestors );
		            foreach ($ancestors as $ancestor_key=>$ancestor) {
		            	$cat = get_term( $ancestor, $taxonomy );
		            	if ($cat) {
		            		array_push($terms, $cat);
		            	}
		            }
		        } elseif (!empty($term)) {
		            // no ancestors exist
		            array_push($terms, $term);
		        }
			}
		}

	} catch(Exception $e) {
		Rollbar::report_message('WooCommerce: error in get_all_terms', 'error',
								array(
									"code" => $e->getCode(),
									"message" => $e->getMessage()
									)
								); 
	}

	if (count($terms) > 0) {
		return $terms;
	} else {
		return null;
	}
}

function render_tags($tags) {
	try {

		$rendered_tags = '[ ';
		$last = count($tags)-1;

		foreach ($tags as $key=>$tag) {
			if ($key != $last) {
				$sep = ', ';	
			} else {
				$sep = '';	
			}
			$rendered_tags .=  '"'. stripslashes($tag->name) .'"'.$sep.'';
		}
		
	} catch(Exception $e) {
		Rollbar::report_message('WooCommerce: error in seg_send_customer', 'error',
								array(
									"code" => $e->getCode(),
									"message" => $e->getMessage()
									)
								); 
	}

	return $rendered_tags.' ]';
}

function seg_post($url, $data) {
	try {

		#$data_string .='[';
		$data_string .= json_encode($data);
		#$data_string .=']';

		$ch = curl_init();

	    if (FALSE === $ch)
	        throw new Exception('Failed to initialise');

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		    'Content-Type: application/json',
		    'Content-Length: ' . strlen($data_string)
		    )
		);

		$result = curl_exec($ch);	

	    if (FALSE === $result)
	        throw new Exception(curl_error($ch), curl_errno($ch));

		$result['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$result['response'] = $result;

	} catch(Exception $e) {
		Rollbar::report_message('WooCommerce: error in seg_post', 'error',
								array(
									"code" => $e->getCode(),
									"message" => $e->getMessage()
									)
								); 
		
		$result['code'] = 0;	
		$result['response'] = $e->getMessage();
	}

	return $result;
}

function seg_send_order($order_id) {
	try {

		$post =  get_post($order_id);
		$order = new WC_Order();
		$order->populate($post);
		$email = $order->billing_email;

		if ($email == '') {
			$user = $order->get_user();
			if ($user != false) {
				$email = $user->user_email;
			}
		}
		
		if ($email != '') {
			$j['DeliveryMethod'] = $order->get_shipping_method();
			$j['DeliveryRevenue'] = $order->get_total_shipping();
			$j['Discount'] = $order->get_total_discount();
			$j['Date'] = get_gmt_from_date($order->order_date);
			$j['Id'] = $order->id;
			$j['Email'] = $email;

			$order_items = $order->get_items();
			
			if ($order_items) {
				$i=0;
				$price = 0;

				foreach ($order_items as $item) {
					$product = new WC_Product($item['item_meta']['_product_id'][0]); 
					$feat_image = wp_get_attachment_url( get_post_thumbnail_id($product->id) );

					$j['OrderLines'][$i]['Quantity'] = $item['item_meta']['_qty'][0];
					$j['OrderLines'][$i]['Id'] = $product->get_sku();
					if ($feat_image != '') {
						$j['OrderLines'][$i]['ImageUrl'] = $feat_image;
					}
					$j['OrderLines'][$i]['Name'] = stripslashes($item['name']);
					$j['OrderLines'][$i]['OriginalPrice'] = $product->get_regular_price();
					$j['OrderLines'][$i]['Price'] = $product->get_price();
					
					$the_terms = get_the_terms($product->id, 'product_cat');
					$cats = get_all_terms($the_terms, 'product_cat');
					if ($cats) {
						$cat_id = 0;
						foreach ($cats as $key=>$cat) {
							$j['OrderLines'][$i]['Categories'][$cat_id] = stripslashes($cat->name);
							$cat_id++;
						}
					}

					$i++;
				}
			}

			$j['Revenue'] = $order->get_subtotal();

			$websiteId = get_option('seg_guid');
			$endpoint = 'https://tracker.segapp.com/woocommerce/'.$websiteId.'/order-placed';

			Rollbar::report_message('WooCommerce: posting order to Seg', 'info', 
									array(
										"order_object" => $j, 
										"siteId" => $websiteId,
										"endpoint" => $endpoint
										)
									); 
			$post = seg_post($endpoint, $j);
			
			$output['status'] = $post['code'];
			$output['message'] = $post['response'];
			$output['order'] = $j;
		} else {
			$output['status'] = 400;
			$output['message'] = 'No email for order #'.$order_id;
		}

	} catch(Exception $e) {
		Rollbar::report_message('WooCommerce: error in seg_send_order', 'error',
								array(
									"code" => $e->getCode(),
									"message" => $e->getMessage()
									)
								); 
	}

	return $output;
}

function seg_send_customer($customer_id) {
	try {

		$email = get_user_meta($customer_id, 'billing_email', true);
		$first_name = get_user_meta($customer_id, 'billing_first_name', true);
		$last_name = get_user_meta($customer_id, 'billing_last_name', true);
		$country = get_user_meta($customer_id, 'billing_country', true);

		if ($email != '') {
			$j['Email'] = $email;
			$j['FirstName'] = $first_name;
			$j['LastName'] = $last_name;
			$j['CountryCode'] = $country;

			$websiteId = get_option('seg_guid');
			$endpoint = 'https://tracker.segapp.com/woocommerce/'.$websiteId.'/update-customer';

			Rollbar::report_message('WooCommerce: posting customer to Seg', 'info',
									array(
										"customer_object" => $j, 
										"siteId" => $websiteId,
										"endpoint" => $endpoint
										)
									); 
			$post = seg_post($endpoint, $j);
			
			$output['status'] = $post['code'];
			$output['message'] = $post['response'];
			$output['customer'] = $j;
		} else {
			$output['status'] = 400;
			$output['message'] = 'No email for customer #'.$customer_id;
		}
		
	} catch(Exception $e) {
		Rollbar::report_message('WooCommerce: error in seg_send_customer', 'error',
								array(
									"code" => $e->getCode(),
									"message" => $e->getMessage()
									)
								); 
	}

	return $output;
}
