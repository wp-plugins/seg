<?php
/**
 * Plugin Name: Seg for WooCommerce
 * Plugin URI: http://woothemes.com/products/seg/
 * Description: Customer opportunity software that shows you how to make more revenue with MailChimp
 * Version: 1.0
 * Author: WooThemes
 * Author URI: http://woothemes.com/
 * Developer: Seg
 * Developer URI: https://www.segapp.com/
 * Text Domain: seg
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

require_once 'includes/rollbar.php';

function get_loggedin_user() {
	global $current_user;

    if ($current_user->ID != '') {
        return array(
            'id' => stripslashes($current_user->user_email)
        );
    }

    return null;
}

$config = array(
    'access_token' => 'c555af86bd524480966ce009b5ba69a9',
    'environment' => 'production',
    'batched' => 'false',
    'person_fn' => 'get_loggedin_user',
    'code_version' => '0.9'
);

$set_exception_handler = false;
$set_error_handler = false;
Rollbar::init($config, $set_exception_handler, $set_error_handler);

$seg = new seg;

#functions
include ''.dirname(__FILE__).'/includes/functions.php';
#settings page
include ''.dirname(__FILE__).'/admin/settings.php';
#woocommerce trackers
include ''.dirname(__FILE__).'/woocommerce/web-tracker.php';

#styles and javascripts
add_action('wp_enqueue_scripts', array($seg, 'scripts'));
add_action('admin_enqueue_scripts',array($seg, 'scripts'));

class seg {
	public $language = 'seg';

	function path($path) {
		return  plugins_url($path,__FILE__);	
	}

	function scripts() {
		wp_register_script('seg-js', plugins_url('/js/scripts.js', __FILE__),array('jquery'));
		wp_localize_script('seg-js', 'seg_js', array( 'ajax' => admin_url( 'admin-ajax.php')));
		wp_enqueue_script('seg-js');
		wp_register_style('seg-css',plugins_url('/css/style.css', __FILE__));
		wp_enqueue_style('seg-css');
	}
}