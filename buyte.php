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
	/* nonce name */
	const NONCE_NAME = 'buyte-ajax-next-nonce';
	/* ajax */
	const AJAX_SUCCESS = 'buyte_success';
	const AJAX_CART = 'buyte_cart';
	/* api */
	const API_BASE_URL = 'https://api.buytecheckout.com/v1/';
	// const API_BASE_URL = 'https://3371887b.au.ngrok.io/v1/';

	/** @var \WC_Buyte single instance of this plugin */
	protected static $instance;

	public $WC_Buyte_Config;
	public $WC_Buyte_Widget;


	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'initialize' ), 100 );
	}

	public function initialize(){
		$this->load_dependencies();

		// Setup admin ajax endpoints
		add_action( 'wp_ajax_buyte_success', array( $this, 'ajax_buyte_success' ) );
		add_action( 'wp_ajax_nopriv_buyte_success', array( $this, 'ajax_buyte_success' ) );
		add_action( 'wp_ajax_buyte_cart', array( $this, 'ajax_buyte_cart' ) );
		add_action( 'wp_ajax_nopriv_buyte_cart', array( $this, 'ajax_buyte_cart' ) );

		// Handle Settings Tab
		$this->handle_config();

		// Handle Widget loads
		$this->handle_widget();
	}

	public function ajax_buyte_success() {
		WC_Buyte_Config::log("buyte_success: Processing Buyte checkout...", WC_Buyte_Config::LOG_LEVEL_INFO);
		// Retrieve JSON payload
		$data = json_decode(file_get_contents('php://input'));
		if(!$data){
			$data = json_decode(json_encode($_POST));
		}

		// check nonce
		if ( ! wp_verify_nonce( $data->nonce, self::NONCE_NAME ) ) {
			header("HTTP/1.1 401 Unauthorized");
   			exit;
		}

		WC_Buyte_Config::log("buyte_success: Nonce verified.", WC_Buyte_Config::LOG_LEVEL_INFO);

		// Ensure price of product and amount authorised are the same.
		if(property_exists($data, 'product_id')){
			$product = wp_get_product($data->product_id);
			$price = 0;
			if(property_exists($data, 'variation_id')){
				$variation = new WC_Product_Variation($data->variation_id);
				$price = WC_Buyte_Util::get_amount($variation->get_price());
			}else{
				$price = WC_Buyte_Util::get_amount($product->get_price());
			}
			if($price != $data->paymentToken->amount){
				header("HTTP/1.1 400 Bad Request");
				wp_send_json_error(array(  // send JSON back
					'error' => "Price of product/variation does not match authorised payment amount."
				));
				exit;
			}
		}

		WC_Buyte_Config::log("buyte_success: Price check passed.", WC_Buyte_Config::LOG_LEVEL_INFO);

		// Get charge
		$charge = $this->create_charge($data->paymentToken);
		WC_Buyte_Config::log("buyte_success: Charge created.", WC_Buyte_Config::LOG_LEVEL_INFO);
		if(property_exists($charge, 'id')){
			$order = property_exists($data, 'product_id') ?
				$this->create_order_from_product(
					$charge,
					$data->product_id,
					property_exists($data, 'variation_id') ? $data->variation_id : null
				) :
				$this->create_order_from_cart($charge);
			WC_Buyte_Config::log("buyte_success: Order created and confirmation url sent.", WC_Buyte_Config::LOG_LEVEL_INFO);
			wp_send_json(array(  // send JSON back
				'redirect_url' => $this->get_confirmation_url($order)
			));
			exit;
		}

		wp_send_json_error(array(  // send JSON back
			'error' => 'Could not process successful Buyte checkout'
		));
		exit;
	}

	public function ajax_buyte_cart() {
		// check nonce
		// $nonce = isset($_GET['nextNonce']) ? $_GET['nextNonce'] : "";
		// if ( ! wp_verify_nonce( $nonce, self::NONCE_NAME ) ) {
		// 	header("HTTP/1.1 401 Unauthorized");
   		// 	exit;
		// }

		$options = $this->WC_Buyte_Widget->get_cart_options();
		wp_send_json($options);
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
		/**
		 * Have the follow JS Object properties sorted in $_POST and a valid Cart, then WC()->checkout()->process_checkout();
		 * You basically just want to use all the code in the class-wc-stripe-payment-request.php file inside Stripe Plugin.
		 *
			billing_first_name:        null !== name ? name.split( ' ' ).slice( 0, 1 ).join( ' ' ) : '',
			billing_last_name:         null !== name ? name.split( ' ' ).slice( 1 ).join( ' ' ) : '',
			billing_company:           '',
			billing_email:             null !== email   ? email : evt.payerEmail,
			billing_phone:             null !== phone   ? phone : evt.payerPhone.replace( '/[() -]/g', '' ),
			billing_country:           null !== billing ? billing.country : '',
			billing_address_1:         null !== billing ? billing.line1 : '',
			billing_address_2:         null !== billing ? billing.line2 : '',
			billing_city:              null !== billing ? billing.city : '',
			billing_state:             null !== billing ? billing.state : '',
			billing_postcode:          null !== billing ? billing.postal_code : '',
			shipping_first_name:       '',
			shipping_last_name:        '',
			shipping_company:          '',
			shipping_country:          '',
			shipping_address_1:        '',
			shipping_address_2:        '',
			shipping_city:             '',
			shipping_state:            '',
			shipping_postcode:         '',
			shipping_method:           [ null === evt.shippingOption ? null : evt.shippingOption.id ],
			order_comments:            '',
			payment_method:            'stripe',
			ship_to_different_address: 1,
			terms:                     1,
			stripe_source:             source.id,
			payment_request_type:      paymentRequestType
		 */
		// ...
	}
	private function create_order_from_cart($charge){
		// Execute WC Proceed Checkout on the existing cart.
	}
	private function create_order_from_product($charge, $product_id, $variation_id = 0){
		// Create a cart
		// Then execute WC Proceed Checkout
	}
	private function get_confirmation_url(WC_Order $order){
		return $order->get_checkout_order_received_url();
	}

	private function create_request($path, $body){
		$data = json_encode($body);
		if(!$data){
			throw new Exception('Cannot encode Buyte request body.');
		}
		$url = self::API_BASE_URL . $path;
		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $this->WC_Buyte_Config->get_secret_key(),
			'Client-Name' => 'Woocommerce',
			'Client-Version' => WC_Buyte_Util::get_wc_version()
		);
		$args = array(
			'headers' => $headers,
			'body' => $data
		);
		return array(
			'url' => $url,
			'args' => $args
		);
	}
	private function execute_request($request) {
		$url = $request['url'];
		$args = $request['args'];
		$response = wp_remote_post($url, $args);
		if(is_wp_error($response)){
			WC_Buyte_Config::log("Error on Request Execute", WC_Buyte_Config::LOG_LEVEL_FATAL);
			WC_Buyte_Config::log($response, WC_Buyte_Config::LOG_LEVEL_FATAL);
			return;
		}
		$response_body = json_decode($response['body']);
		return $response_body;
	}
	private function create_charge(object $paymentToken){
		$request = $this->create_request('charges', array(
			'source' => $paymentToken->id,
			'amount' => (int) $paymentToken->amount,
			'currency' => $paymentToken->currency
		));
		WC_Buyte_Config::log("create_charge: Attempting to charge Buyte Payment Token: " . $paymentToken->id, WC_Buyte_Config::LOG_LEVEL_INFO);
		WC_Buyte_Config::log($request, WC_Buyte_Config::LOG_LEVEL_DEBUG);
		$response = $this->execute_request($request);
		if(empty($response)){
			WC_Buyte_Config::log("create_charge: Could not create charge", WC_Buyte_Config::LOG_LEVEL_FATAL);
			throw new Exception("Could not create Charge");
		}
		WC_Buyte_Config::log("create_charge: Successfully created charge: " . $response->id, WC_Buyte_Config::LOG_LEVEL_INFO);
		WC_Buyte_Config::log($response, WC_Buyte_Config::LOG_LEVEL_DEBUG);
		return $response;
	}

	private function load_dependencies(){
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-config.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-widget.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-util.php';
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
}

function WC_Buyte() {
	return WC_Buyte::instance();
}

$GLOBALS['WC_Buyte'] = WC_Buyte();