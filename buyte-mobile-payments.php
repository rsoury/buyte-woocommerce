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

	public $WC_Buyte_Mobile_Payments_Config;
	public $WC_Buyte_Mobile_Payments_Widget;


	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'initialize' ) );
	}

	public function initialize(){
		$this->load_dependencies();

		// Set the custom order number on the new order.  we hook into wp_insert_post for orders which are created
		// add_action( 'wp_insert_post', array( $this, 'on_order_creation' ), 10, 2 );
		add_action( 'parse_request', array($this, 'process_buyte_actions') );

		// Handle Settings Tab
		$this->handle_config();

		// Handle Widget loads
		$this->handle_widget();
	}

	 /**
     * This is the function to process the custom defined endpoint
     *
     * @param $wp
     * @return bool
     */
    public function process_buyte_actions($wp)
    {
        $query_vars = $wp->query_vars;

        if (isset($query_vars['p']) == false || $query_vars['p'] != "buyte") {
            return false;
        }

        if (isset($query_vars['route']) == false || $query_vars['route'] != 'payment') {
            return false;
        }
        
        switch ($query_vars['action_type']) {
        	case 'success':
        		if(isset($query_vars['product_id']) == false){
        			$order = $this->create_order_from_cart();
        		}else{
    				$order = $this->create_order_from_product();
        		}
        		if(isset($order)){
        			echo $this->get_confirmation_url($order);
        		}
        		break;
    		case 'cart':
    			$options = $this->WC_Buyte_Mobile_Payments_Widget->get_cart_options();
    			echo json_encode($options);
            default:
            	break;
        }
        exit;
    }

    public function basename(){
    	return plugin_basename(__FILE__);
    }

    public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function create_order_from_cart(){
		
	}
	private function create_order_from_product(){

	}
	private function get_confirmation_url(WC_Order $order){
		return $order->get_checkout_order_received_url();
	}

	private function load_dependencies(){
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-mobile-payments-config.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-mobile-payments-widget.php';
	}
	private function handle_config(){
		$this->WC_Buyte_Mobile_Payments_Config = new WC_Buyte_Mobile_Payments_Config($this);
		$this->WC_Buyte_Mobile_Payments_Config->init();
	}

	private function handle_widget(){
		$this->WC_Buyte_Mobile_Payments_Widget = new WC_Buyte_Mobile_Payments_Widget($this);
		$this->WC_Buyte_Mobile_Payments_Widget->init_hooks();
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