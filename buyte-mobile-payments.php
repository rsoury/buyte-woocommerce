<?php

/**
 * Plugin Name:       Buyte - Apple Pay WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/buyte-woocommerce-plugin/
 * Description:       Buyte provides a widget that allows you to implement Apple Pay with a simple snippet and have it exposed across all browsers and devices. No longer are your customers forced to use Safari to pay with Apple Pay. Buyte also provides full support for integrations, which means a faulty SSL certificate won't block your streamlined mobile payment method.
 * Version:           1.0.0
 * Author:            Buyte Mobile Payments
 * Author URI:        https://buyte.co/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Github URI:        https://github.com/rsoury/buyte-woocommerce
 *
 *
 * @version  1.0.0
 * @package  Buyte Mobile Payments
 * @author   Buyte Mobile Payments
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if(!WC_Buyte_Mobile_Payments::is_woocommerce_active()){
	return;
}

class WC_Buyte_Mobile_Payments{

	/* version number */
	const VERSION = '1.0.0';

	/** @var \WC_Buyte_Mobile_Payments single instance of this plugin */
	protected static $instance;

	public $id = 'buyte';
	public $settings_title = 'Buyte';
	public $settings_description = 'Buyte provides a widget that allows you to implement Apple Pay and have it exposed across all browsers and devices.';

	public $WC_Buyte_Mobile_Payments_Config;


	public function __construct() {
		$this->load_dependencies();
		$this->run();

		add_action( 'plugins_loaded', array( $this, 'initialize' ) );
	}

	public function initialize(){

		// Set the custom order number on the new order.  we hook into wp_insert_post for orders which are created
		// add_action( 'wp_insert_post', array( $this, 'on_order_creation' ), 10, 2 );

		// Handle Settings Tab
		$this->WC_Buyte_Mobile_Payments_Config->init();
	}

	public function on_order_creation(){

	}

    public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function load_dependencies(){
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wc-buyte-mobile-payments-config.php';
	}
	private function run(){
		$this->WC_Buyte_Mobile_Payments_Config = new WC_Buyte_Mobile_Payments_Config($this);
	}

	public static function is_woocommerce_active(){
    	$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
    }
    private static function get_wc_version() {
		return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
	}
	private static function is_wc_version_gte_3_0() {
		return self::get_wc_version() && version_compare( self::get_wc_version(), '3.0', '>=' );
	}
}

function wc_buyte_mobile_payments() {
	return WC_Buyte_Mobile_Payments::instance();
}

$GLOBALS['wc_buyte_mobile_payments'] = wc_buyte_mobile_payments();