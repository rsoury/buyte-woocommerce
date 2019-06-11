<?php

/**
 * Plugin Name:       Buyte - Apple Pay and Google Pay in a single install
 * Plugin URI:        https://wordpress.org/plugins/buyte-woocommerce-plugin/
 * Description:       Offer your customers Apple Pay and Google Pay in a single install. By integrating Buyte into your e-commerce website, your visitors can securely checkout with their mobile wallet.
 * Version:           0.1.0
 * Author:            Buyte
 * Author URI:        https://www.buytecheckout.com/
 * License:           GPL-2.0+
 * Github URI:        https://github.com/rsoury/buyte-woocommerce
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 *
 *
 * @version  0.1.0
 * @package  Buyte - Apple Pay and Google Pay in a single install
 * @author   Buyte
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if(!WC_Buyte::is_woocommerce_active()){
	return;
}

class WC_Buyte{

	/* version number */
	const VERSION = '0.1.0';

	/** @var \WC_Buyte single instance of this plugin */
	protected static $instance;

	public $WC_Buyte_Widget;


	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'initialize' ), 100 );
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
		self::debug_log($query_vars); // TODO: Can't find query_var action_type

        if ((isset($query_vars['p']) ? $query_vars['p'] != "buyte" : true) || !isset($query_vars['action_type'])) {
            return false;
        }

        switch ($query_vars['action_type']) {
            case 'success':
                if (isset($query_vars['product_id']) == false) {
                    $order = $this->create_order_from_cart();
                } else {
                    $order = $this->create_order_from_product();
                }
                if (isset($order)) {
                    echo $this->get_confirmation_url($order);
                }
                break;
            case 'cart':
                $options = $this->WC_Buyte_Widget->get_cart_options();
                echo json_encode($options);
				break;
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

	private function create_order(){
		// ...
	}
	private function create_order_from_cart(){
		// ...
	}
	private function create_order_from_product(){
		// ...
	}
	private function get_confirmation_url(WC_Order $order){
		return $order->get_checkout_order_received_url();
	}

	private function load_dependencies(){
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-config.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-widget.php';
	}
	private function handle_config(){
		$this->WC_Buyte_Config = new WC_Buyte_Config($this);
		$this->WC_Buyte_Config->init();
	}
	private function handle_widget(){
		$this->WC_Buyte_Widget = new WC_Buyte_Widget($this);
		$this->WC_Buyte_Widget->init_hooks();
	}

	public static function is_woocommerce_active(){
    	$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
	}
	public static function debug_log($log){
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) );
		} else {
			error_log( $log );
		}
	}

    private static function get_wc_version() {
		return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
	}
	private static function is_wc_version_gte_3_0() {
		return self::get_wc_version() && version_compare( self::get_wc_version(), '3.0', '>=' );
	}
}

function WC_Buyte() {
	return WC_Buyte::instance();
}

$GLOBALS['WC_Buyte'] = WC_Buyte();