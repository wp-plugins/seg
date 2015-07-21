<?php

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

$seg_admin = new seg_admin;

add_action( 'admin_menu', array($seg_admin, 'menu') );
add_action( 'admin_notices', array($seg_admin, 'check_import') );

add_action( 'wp_ajax_seg_get_all_orders', array($seg_admin, 'get_all_orders') );
add_action( 'wp_ajax_nopriv_seg_get_all_orders',  array($seg_admin, 'get_all_orders') );

add_action( 'wp_ajax_seg_get_all_customers', array($seg_admin, 'get_all_customers') );
add_action( 'wp_ajax_nopriv_seg_get_all_customers',  array($seg_admin, 'get_all_customers') );

add_action( 'wp_ajax_seg_import_order_id', array($seg_admin, 'import_order_id') );
add_action( 'wp_ajax_nopriv_seg_import_order_id',  array($seg_admin, 'import_order_id') );

add_action( 'wp_ajax_seg_import_customer_id', array($seg_admin, 'import_customer_id') );
add_action( 'wp_ajax_nopriv_seg_import_customer_id',  array($seg_admin, 'import_customer_id') );

add_action( 'wp_ajax_seg_import_finish', array($seg_admin, 'import_finish') );
add_action( 'wp_ajax_nopriv_seg_import_finish',  array($seg_admin, 'import_finish') );

class seg_admin {
	function menu() {
		try {

			add_menu_page('Seg', 'Seg', 'manage_options', 'seg', array($this, 'settings'), seg::path('images/seg_icon.png'));

		} catch(Exception $e) {
			Rollbar::report_message('WooCommerce: error in menu', 'error',
									array(
										"code" => $e->getCode(),
										"message" => $e->getMessage()
										)
									); 
		}
	}

	function check_import() {
		try {

			if (get_option('seg_first_time_order_import' ) == false) {
				$class = 'error';
				$message = 'Welcome to Seg! To get started we need to import your existing WooCommerce orders into Seg. <a href="admin.php?page=seg" class="button">To start the import click here.</a>';
		        echo"<div class=\"$class\"> <p>$message</p></div>";
			}

		} catch(Exception $e) {
			Rollbar::report_message('WooCommerce: error in check_import', 'error',
									array(
										"code" => $e->getCode(),
										"message" => $e->getMessage()
										)
									); 
		}
	}

	function settings() {
		try {

			global $current_user;

			add_thickbox();

			if ($_GET['reset-seg'] != '') {
				update_option('seg_guid', '');
				update_option('seg_first_time_order_import', false);
			}

			do_action('seg/admin/before_header');
			
			if (get_option('seg_first_time_order_import' ) == false) {
				echo '<h1><a href="https://www.segapp.com/" target="_blank"><img src="'.seg::path('images/seg_logo.png').'" width="200"></a></h1>';
				echo '<p>Let\'s get you started. Seg shows you how to easily and automatically make more revenue from MailChimp by understanding customer preferences and behaviour.</p>';
			} else {
				echo '<h1><a href="https://www.segapp.com/" target="_blank"><img src="'.seg::path('images/seg_logo.png').'" width="200"></a></h1>';
				echo '<p>Seg shows you how to easily and automatically make more revenue from MailChimp by understanding customer preferences and behaviour.</p>';
			}

			do_action('seg/admin/after_header');
			
			if ($_POST['save-seg-config'] != '') {
				$seg_guid = trim($_POST['seg_guid']);
				$seg_guid = str_replace('\\', "", $seg_guid);
				$seg_guid = str_replace('"', "", $seg_guid);
				$seg_guid = str_replace("'", "", $seg_guid);
				$seg_guid = trim($seg_guid);

				if (strlen($seg_guid) == 0 || preg_match('/^\{?[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}\}?$/i', $seg_guid)) {
					update_option('seg_guid', $seg_guid);
					echo '<div class="updated"><p>'.__('Seg Unique Website Id updated', 'seg').'</p></div>';
				} else {
					echo '<div class="error"><p>'.__('Please enter a valid Seg Website Id', 'seg').'</p></div>';

				}
			}

			if (get_option('seg_first_time_order_import' ) == false || get_option('seg_guid') == '') {
				$email = stripslashes($current_user->user_email);
				$firstName = stripslashes($current_user->user_firstname);
				$lastName = stripslashes($current_user->user_lastname);
				$shop_page_url = get_permalink( woocommerce_get_page_id( 'shop' ) );
				$site_title = get_bloginfo('name');

				$content1style = 'block';
				$content2style = 'none';
				$content3style = 'none';

				if ($_POST['save-seg-config'] != '') {
					$content1style = 'none';
					$content2style = 'block';
					$content3style = 'none';
				}

				if (get_option('seg_guid') != '') {
					$content1style = 'none';
					$content2style = 'none';
					$content3style = 'block';
				}

				echo '<div class="seg-setup-block" id="seg-block-1">';
				echo '    <h2 class="seg-setup-header"><span>1)</span> Create an Account</h2>';
				echo '    <div class="seg-setup-content" id="seg-content-1" style="display: '.$content1style.';">';
				echo '    	<form action="https://www.segapp.com/signup" method="POST" target="_blank">';
				echo '        <p>Firstly You\'ll need a Seg account, we have a totally free plan that\'s awesome, <a href="http://getseg.com/pricing.html" target="_blank">sign up for free now</a>.</p>';
				echo '        <table class="form-table">';
				echo '            <tr>';
				echo '        		  <th><label for="user_email">Email</label></th>';
				echo '        		  <td><input type="text" name="Email" id="user_email" value="'.$email.'" class="regular-text"></td>';
				echo '        	  </tr>';
				echo '        	  <tr>';
				echo '        		  <th><label for="first_name">First Name</label></th>';
				echo '        		  <td><input type="text" name="FirstName" id="first_name" value="'.$firstName.'" class="regular-text"></td>';
				echo '        	  </tr>';
				echo '        	  <tr>';
				echo '        		  <th><label for="last_name">Last Name</label></th>';
				echo '        		  <td><input type="text" name="LastName" id="last_name" value="'.$lastName.'" class="regular-text"></td>';
				echo '        	  </tr>';
				echo '            <tr>';
				echo '        		  <th><label for="user_company">Company Name</label></th>';
				echo '        		  <td><input type="text" name="CompanyName" id="user_company" value="'.$site_title.'" class="regular-text"></td>';
				echo '        	  </tr>';
				echo '        	  <tr>';
				echo '        		  <th><label for="website_name">Website Name</label></th>';
				echo '        		  <td><input type="text" name="WebsiteName" id="website_name" value="'.$site_title.'" class="regular-text"></td>';
				echo '        	  </tr>';
				echo '        	  <tr>';
				echo '        		  <th><label for="website_url">Website Url</label></th>';
				echo '        		  <td><input type="text" name="Url" id="website_url" value="'.$shop_page_url.'" class="regular-text"></td>';
				echo '        	  </tr>';
				echo '        </table>';
				echo '        <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Create Seg Account" target="_blank">';
				echo '        &nbsp;&nbsp;&nbsp;<a href="#" class="seg-have-account">I have a Seg account</a></p>';
				echo '    	</form>';
				echo '    </div>';
				echo '</div>';
				echo '<div class="seg-setup-block" id="seg-block-2">';
				echo '    <h2 class="seg-setup-header seg-have-account" style="cursor: pointer;"><span>2)</span> Enter Your Unique Website Id</h2>';
				echo '    <div class="seg-setup-content" id="seg-content-2" style="display: '.$content2style.'">';
				echo '    <p style="position: relative">Once you have a Seg account, we need you to enter your unique website id. <a href="https://www.segapp.com/docs" target="_blank">You can get your unique website id from here</a>.';
				echo '    <a href="'.seg::path('images/websiteid.png').'" class="thickbox" target="_blank"><img src="'.seg::path('images/websiteid.png').'" width="100" style="position: absolute; right: 0px;"></a></p>';
				$this -> get_website_id('button-primary');
				echo '    </div>';
				echo '</div>';
				echo '<div class="seg-setup-block" id="seg-block-3">';
				echo '    <h2 class="seg-setup-header"><span>3)</span> Import Orders into Seg</h2>';
				echo '    <div class="seg-setup-content" id="seg-content-3" style="display: '.$content3style.'">';
				echo '        <p>'.__('Congratulations! You are minutes away from exploring your customer data and making more revenue with MailChimp.', 'seg').'</p>';
				echo '        <p>'.__('We will import your customer data and order history into Seg, to get started simply click the "Import Customers & Orders into Seg" button. This process can take several minutes depending on how many orders you have.', 'seg').'</p>';
				$this -> show_import_button('Import Customers & Orders into Seg', 'button-primary');
				echo '    </div>';
				echo '</div>';
			} else {
				echo '<div>&nbsp;</div>';
				echo '<h2>System Status</h2>';
				echo '<h4 style="color: #5ea091;"><span alt="f147" class="dashicons dashicons-yes"></span> Tracking code installed & all orders being received fine. Everything is looking good.</h4>';
				
				echo '<a href="https://www.segapp.com/" target="_blank" class="button button-primary button-large">Log In to Your Seg Account <span alt="f504" style="font-size: 15px; height: 15px; width: 15px; line-height: 1.8;" class="dashicons dashicons-external"></span></a>';

				echo '<div>&nbsp;</div>';
				echo '<hr>';
				echo '<h2>Settings</h2>';
				$this -> get_website_id('');

				echo '<div>&nbsp;</div>';
				echo '<hr>';
				echo '<h2>Re-Import Customers &amp; Orders into Seg</h2>';
				echo '<p>'.__('Your historical orders have been imported already, so you will not need to do this again, unless a member of our support team asks you to :)', 'seg').'</p>';
				echo '<p>'.__('All new orders placed from initial setup onwards are sent to Seg automatically.', 'seg').'</p>';
				$this -> show_import_button('Re-Import Customers & Orders into Seg', '');
			}

			do_action('seg/admin/above_settings');

			do_action('seg/admin/form_fields');
			echo'</div>';
			 
			#do_action('seg/admin/under_settings');

		} catch(Exception $e) {
			Rollbar::report_message('WooCommerce: error in settings', 'error',
									array(
										"code" => $e->getCode(),
										"message" => $e->getMessage()
										)
									); 
		}
	}

	function get_website_id($class) {
		try {

			echo '<form action="admin.php?page=seg" method="post" class="seg-form">';
			echo '    <table class="form-table">';
			echo '        <tr>';
			echo '            <th><label for="seg_guid">'.__("Your Unique Website Id", 'seg').'</label></th>';
			echo '            <td><input type="text" name="seg_guid" id="seg_guid" value="'.get_option('seg_guid').'" class="regular-text"></td>';
			echo '        </tr>';
			echo '    </table>';
			echo '    <p><input type="submit" name="save-seg-config" class="button '.$class.'" value="'.__("Save Website Id", 'woocommerce-seg').'"></p>';
			echo' </form>';

		} catch(Exception $e) {
			Rollbar::report_message('WooCommerce: error in get_website_id', 'error',
									array(
										"code" => $e->getCode(),
										"message" => $e->getMessage()
										)
									); 
		}
	}
	
	function show_import_button($label, $class) {
		try {

			if(get_option('seg_guid') != false) {
				echo '<p class="submit"><a href="#" class="button '.$class.' seg-start-import">'.__($label, 'seg').'</a></p>
					  <div class="seg-import-screen" style="display:none;padding:10px;margin-bottom:10px;background-color:#000;color:#FFF;height:300px;overflow-y:auto;">
					  </div>';	
			} else {
				echo'<p>You need to enter your Seg Website Id above before you can import your orders into Seg for processing.</p>';
			}

		} catch(Exception $e) {
			Rollbar::report_message('WooCommerce: error in show_import_button', 'error',
									array(
										"code" => $e->getCode(),
										"message" => $e->getMessage()
										)
									); 
		}
	}
	
	function get_all_orders() {
		try {
			
			$j['message'] = __('No orders to import.', 'seg');
			$j['success'] = 0;
			
			$args = array(
			  'post_type' => 'shop_order',
			  'post_status' => array('wc-completed'),
			  'meta_key' => '_customer_user',
			  'posts_per_page' => '-1'
			);
			
			$my_query = new WP_Query($args);
			
			$orders = $my_query->posts;
			
			if ($orders) {
				foreach ($orders as $order) {
					$j['orders'][] = $order->ID;
				}

				$j['message'] = 'Found '.count($orders).' orders. Starting import...';
				$j['success'] = 1;
			}
		
			echo json_encode($j);

		} catch(Exception $e) {
			Rollbar::report_message('WooCommerce: error in get_all_orders', 'error',
									array(
										"code" => $e->getCode(),
										"message" => $e->getMessage()
										)
									); 
		}

		die();
	}
	
	function get_all_customers() {
		try {

			$j['message'] = __('No customers to import.', 'seg');
			$j['success'] = 0;
			
			$customers = get_users();
			
			if ($customers) {
				foreach ($customers as $customer) {
					$j['customers'][] = $customer->ID;
				}

				$j['message'] = 'Found '.count($customers).' customers. Starting import...';
				$j['success'] = 1;
			}
		
			echo json_encode($j);

		} catch(Exception $e) {
			Rollbar::report_message('WooCommerce: error in get_all_customers', 'error',
									array(
										"code" => $e->getCode(),
										"message" => $e->getMessage()
										)
									); 
		}

		die();
	}
	
	function import_order_id() {
		try {

			$order_id = $_POST['id'];		
			$response = seg_send_order($order_id);
			$o['status'] = $response['status'];
			$o['order'] = $response['order'];
			
			if ($response['status'] == '200' || $response['status'] == '201' || $response['status'] == '202') {
				$o['message'] =  'Imported order #'.$order_id;
				$o['success'] = 1;
			} else {
				$o['message'] = 'Could not import order #'.$order_id.' (code '.$response['status'].', reason "'.$response['message'].'")';
				$o['success'] = 0;
			}
			
			echo json_encode($o);

		} catch(Exception $e) {
			Rollbar::report_message('WooCommerce: error in import_order_id', 'error',
									array(
										"code" => $e->getCode(),
										"message" => $e->getMessage()
										)
									); 
		}

		die();
	}
	
	function import_customer_id() {
		try {

			$customer_id = $_POST['id'];		
			$response = seg_send_customer($customer_id);
			$o['status'] = $response['status'];
			$o['customer'] = $response['customer'];
			
			if ($response['status'] == '200' || $response['status'] == '201' || $response['status'] == '202') {
				$o['message'] =  'Imported customer #'.$customer_id;
				$o['success'] = 1;
			} else {
				$o['message'] = 'Could not import customer #'.$customer_id.' (code '.$response['status'].', reason "'.$response['message'].'")';
				$o['success'] = 0;
			}
			
			echo json_encode($o);

		} catch(Exception $e) {
			Rollbar::report_message('WooCommerce: error in import_customer_id', 'error',
									array(
										"code" => $e->getCode(),
										"message" => $e->getMessage()
										)
									); 
		}

		die();
	}
	
	function import_finish() {
		try {

			if (get_option('seg_first_time_order_import' ) == false) {
				$o['message'] = ''.__('Finished importing customers & orders into Seg. The next step is to <a href="https://www.segapp.com/websites" target="_blank">log in to Seg</a> and connect your MailChimp account.', 'seg').'';
				update_option('seg_first_time_order_import', 1);
			} else {
				$o['message'] = ''.__('Finished importing customers & orders into Seg.', 'seg').'';
			}

			$o['success'] = 1;

			echo json_encode($o);	

		} catch(Exception $e) {
			Rollbar::report_message('WooCommerce: error in import_finish', 'error',
									array(
										"code" => $e->getCode(),
										"message" => $e->getMessage()
										)
									); 
		}

		die();	
	}
}
