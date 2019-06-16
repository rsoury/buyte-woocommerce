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

		// Handle Payment Gateway
		add_filter( 'woocommerce_payment_gateways', array( $this, 'handle_payment_gateway' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'gateway_availability' ));

		// Add order meta data after order process
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'add_order_meta' ), 10, 1 );
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

	/**
	 * ajax_buyte_success
	 *
	 * Ajax function handler that accepts Buyte payment tokens and context on checkout request,
	 * and processes a checkout and order.
	 *
	 * Uses native WC checkout process for security and compatibility.
	 *
	 * @return void
	 */
	public function ajax_buyte_success() {
		WC_Buyte_Config::log("buyte_success: Processing Buyte checkout...", WC_Buyte_Config::LOG_LEVEL_INFO);
		// Retrieve JSON payload
		$data = json_decode(file_get_contents('php://input'));
		if(!$data){
			$data = json_decode(json_encode($_POST));
		}

		// check nonce
		if ( ! wp_verify_nonce( $data->nextNonce, self::NONCE_NAME ) ) {
			header("HTTP/1.1 401 Unauthorized");
   			exit;
		}

		WC_Buyte_Config::log("buyte_success: Nonce verified.", WC_Buyte_Config::LOG_LEVEL_INFO);

		// Define variables
		$product_id = property_exists($data, 'productId') ? $data->productId : 0;
		$variation_id = property_exists($data, 'variationId') ? $data->variationId : 0;
		$quantity = property_exists($data, 'quantity') ? absint( $data->quantity ) : 1;

		// Ensure price of product and amount authorised are the same.
		if(!empty($product_id)){
			$product = wc_get_product($product_id);
			$price = 0;
			if(!empty($variation_id)){
				$variation = new WC_Product_Variation($variation_id);
				$price = WC_Buyte_Util::get_amount(
					WC_Buyte_Util::is_wc_lt( '3.0' ) ? $variation->price : $variation->get_price()
				);
			}else{
				$price = WC_Buyte_Util::get_amount(
					WC_Buyte_Util::is_wc_lt( '3.0' ) ? $product->price : $product->get_price()
				);
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
		if( property_exists( $charge, 'id' ) ){
			// Order functions use a WC checkout method that sends a redirect url to the frontend for us.
			if( empty( $product_id ) ){
				$this->create_order_from_cart( $charge );
			} else {
				$this->create_order_from_product( $charge, $product_id, $variation_id, $quantity );
			}
			WC_Buyte_Config::log("buyte_success: Order created and confirmation url sent.", WC_Buyte_Config::LOG_LEVEL_INFO);
			exit;
		}else{
			WC_Buyte_Config::log("buyte_success: Charge does not have Id. Contact Buyte Support.", WC_Buyte_Config::LOG_LEVEL_WARN);
		}

		wp_send_json_error(array(  // send JSON back
			'error' => 'Could not process Buyte checkout'
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

	/**
	 * Load all dependency files for this plugin.
	 *
	 */
	public function load_dependencies(){
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-config.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-widget.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-util.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-buyte-payment-gateway.php';
	}

	/**
	 * Setup and Initiate Config
	 *
	 * @return void
	 */
	public function handle_config(){
		$this->WC_Buyte_Config = new WC_Buyte_Config($this);
		$this->WC_Buyte_Config->init();
	}
	/**
	 * Setup and initiate the Buyte widget
	 *
	 * @return void
	 */
	public function handle_widget(){
		$this->WC_Buyte_Widget = new WC_Buyte_Widget($this);
		$this->WC_Buyte_Widget->init_hooks();
	}
	/**
	 * Setup and initiate Buyte as a means of processing order payments. -- payment gateway.
	 *
	 * @param array[string] $methods
	 * @return void
	 */
	public function handle_payment_gateway($methods) {
		$methods[] = 'WC_Buyte_Payment_Gateway';
    	return $methods;
	}
	/**
	 * gateway_availability
	 *
	 * Ensures Buyte Gateway only available when order params inclure a Buyte Charge Id.
	 *
	 * @param [type] $gateways
	 * @return void
	 */
	public function gateway_availability($gateways){
		$id = WC_Buyte_Config::get_id();
		if(!isset($gateways[$id])){
			return $gateways;
		}

		if ( !$this->is_buyte_checkout() ) {
			unset($gateways[$id]);
		}

		return $gateways;
	}

	/**
	 * is_buyte_checkout
	 *
	 * Checks if the current checkout request is a Buyte checkout
	 *
	 * @return boolean
	 */
	public function is_buyte_checkout() {
		if( isset( $_POST['buyte_charge'] ) ){
			return !empty( $_POST['buyte_charge'] );
		}
		return false;
	}

	/**
	 * add_order_meta
	 *
	 * Adds Buyte related metadata to the order
	 *
	 * @param [type] $order_id
	 * @return void
	 */
	public function add_order_meta( $order_id ){
		if ( !$this->is_buyte_checkout() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		$charge_id = $_POST['buyte_charge'];
		$payment_source_id = $_POST['buyte_payment_source'];
		$payment_type = $_POST['buyte_payment_type'];

		$method_title = $payment_type . ' ('. $this->WC_Buyte_Config->label .')';
		if ( WC_Buyte_Util::is_wc_lt( '3.0' ) ) {
			update_post_meta( $order_id, '_payment_method_title', $method_title );
		} else {
			$order->set_payment_method_title( $method_title );
			$order->save();
		}

		update_post_meta( $order_id, '_buyte_charge_id', $charge_id );
		update_post_meta( $order_id, '_buyte_payment_source_id', $payment_source_id );
	}

	/**
	 * create_order
	 *
	 * Creates new Post Data to use with session cart to "natively" process checkout
	 * Native WC checkout processing ensures security and compatibility.
	 *
	 * @param object $charge
	 * @return void
	 */
	private function create_order($charge){
		if(!property_exists($charge, 'id')){
			$errMsg = "No Buyte charge Id";
			WC_Buyte_Config::log($errMsg, WC_Buyte_Config::LOG_LEVEL_ERROR);
			throw new Exception($errMsg);
		}
		if(!property_exists($charge, 'customer')){
			$errMsg = "No customer information in Buyte charge";
			WC_Buyte_Config::log($errMsg, WC_Buyte_Config::LOG_LEVEL_ERROR);
			throw new Exception($errMsg);
		}

		$customer = $charge->customer;

		// Customer Name
		$first_name = '';
		if(property_exists($customer, 'givenName')){
			$first_name = $customer->givenName;
		}
		$last_name = '';
		if(property_exists($customer, 'familyName')){
			$last_name = $customer->familyName;
		}
		if(property_exists($customer, 'name')){
			$split_name = WC_Buyte_Util::split_name($customer->name);
			if(empty($first_name)){
				$first_name = $split_name[0];
			}
			if(empty($last_name)){
				$last_name = $split_name[1];
			}
		}

		$postdata = array(
			'shipping_first_name' => $first_name,
			'shipping_last_name' => $last_name,
			'billing_first_name' => $first_name,
			'billing_last_name' => $last_name,
			'shipping_company' => '',
			'shipping_country' =>
				isset($customer->shippingAddress->country) ?
					$customer->shippingAddress->country :
					(isset($customer->shippingAddress->countryCode) ? $customer->shippingAddress->countryCode : ''),
			'shipping_address_1' =>
				isset($customer->shippingAddress->addressLines) ?
					(sizeof($customer->shippingAddress->addressLines) > 0 ? $customer->shippingAddress->addressLines[0] : '') :
					'',
			'shipping_address_2' =>
				isset($customer->shippingAddress->addressLines) ?
					(sizeof($customer->shippingAddress->addressLines) > 1 ? $customer->shippingAddress->addressLines[1] : '') :
					'',
			'shipping_city' => isset($customer->shippingAddress->locality) ? $customer->shippingAddress->locality : '',
			'shipping_state' => isset($customer->shippingAddress->administrativeArea) ? $customer->shippingAddress->administrativeArea : '',
			'shipping_postcode' => isset($customer->shippingAddress->postalCode) ? $customer->shippingAddress->postalCode : '',
		);
		if(isset($customer->billingAddress) ? !empty((array) $customer->billingAddress) : false){
			$postdata += array(
				'billing_company' => '',
				'billing_country' =>
					isset($customer->billingAddress->country) ?
						$customer->billingAddress->country :
						(isset($customer->billingAddress->countryCode) ? $customer->billingAddress->countryCode : ''),
				'billing_address_1' =>
					isset($customer->billingAddress->addressLines) ?
						(sizeof($customer->billingAddress->addressLines) > 0 ? $customer->billingAddress->addressLines[0] : '') :
						'',
				'billing_address_2' =>
					isset($customer->billingAddress->addressLines) ?
						(sizeof($customer->billingAddress->addressLines) > 1 ? $customer->billingAddress->addressLines[1] : '') :
						'',
				'billing_city' => isset($customer->billingAddress->locality) ? $customer->billingAddress->locality : '',
				'billing_state' => isset($customer->billingAddress->administrativeArea) ? $customer->billingAddress->administrativeArea : '',
				'billing_postcode' => isset($customer->billingAddress->postalCode) ? $customer->billingAddress->postalCode : '',
			);
		}else{
			$postdata += array(
				'billing_company' => $postdata['shipping_company'],
				'billing_country' => $postdata['shipping_country'],
				'billing_address_1' => $postdata['shipping_address_1'],
				'billing_address_2' => $postdata['shipping_address_2'],
				'billing_city' => $postdata['shipping_city'],
				'billing_state' => $postdata['shipping_state'],
				'billing_postcode' => $postdata['shipping_postcode']
			);
		}

		// Comments
		$comments = "Checkout completed with Buyte";
		$payment_type = '';
		if(isset($charge->source->paymentMethod->name)){
			$payment_type = $charge->source->paymentMethod->name;
			$comments .= "'s " . $charge->source->paymentMethod->name . ".";
		}
		if(isset($charge->source->shippingMethod)){
			$shipping_method_name = isset($charge->source->shippingMethod->label) ? $charge->source->shippingMethod->label : '';
			$shipping_method_description = isset($charge->source->shippingMethod->description) ? $charge->source->shippingMethod->description : '';
			$shipping_method_rate = isset($charge->source->shippingMethod->rate) ?
				($charge->source->shippingMethod->rate > 0 ? wc_price( $charge->source->shippingMethod->rate / 100 ) : "Free")
				: '';
			$comments .= "\n
				Shipping Method:
					Name: ". $shipping_method_name ."
					Description: ". $shipping_method_description ."
					Rate: ". $shipping_method_rate ."
			";
		}

		// Recreate $_POST for checkout
		$postdata += array(
			'billing_email' => property_exists($customer, 'emailAddress') ? $customer->emailAddress : null,
			'billing_phone' => property_exists($customer, 'phoneNumber') ? $customer->phoneNumber : null,
			'shipping_method' => null,
			'order_comments' => $comments,
			'payment_method' => $this->WC_Buyte_Config->id,
			'ship_to_different_address' => 1,
			'terms' => 1,
			'buyte_charge' => $charge->id,
			'buyte_payment_source' => $charge->source->id,
			'buyte_payment_type' => $payment_type,
			'buyte_shipping_rate' => isset($charge->source->shippingMethod->rate) ? $charge->source->shippingMethod->rate : 0
		);

		WC_Buyte_Config::log("create_order: Post data set", WC_Buyte_Config::LOG_LEVEL_DEBUG);
		WC_Buyte_Config::log($postdata, WC_Buyte_Config::LOG_LEVEL_DEBUG);

		// Required to process checkout using WC
		$_REQUEST['woocommerce-process-checkout-nonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST = $postdata;

		// Execute WC Proceed Checkout on the existing cart.

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		WC()->checkout()->process_checkout();

		return;
	}
	/**
	 * set_shipping
	 *
	 * Set shipping rate in charge to cart totals if it exists.
	 *
	 * @param [type] $charge
	 * @return void
	 */
	private function set_shipping($charge){
		// Reset any shipping settings
		WC()->shipping->reset_shipping();

		// Set shipping data
		if(!isset($charge->source->shippingMethod)){
			return;
		}

		$rate = $charge->source->shippingMethod->rate;

		if(!empty( $rate )){
			$price = wc_price( $rate / 100 );
			WC()->cart->set_shipping_total( $price );
		}
	}
	/**
	 * create_order_from_cart
	 *
	 * Uses existing cart to process checkout and create order
	 *
	 * @param object $charge
	 * @return void
	 */
	private function create_order_from_cart($charge){
		// Cart is already set here.
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'woocommerce' ) );
			exit;
		}

		$this->set_shipping($charge);

		// Calculate cart totals
		WC()->cart->calculate_totals();

		return $this->create_order($charge);
	}
	/**
	 * create_order_from_product
	 * 
	 * Empties existing cart, creates new cart with given product/variant to process checkout and create order
	 *
	 * @param object $charge
	 * @param integer $product_id
	 * @param integer $variation_id
	 * @param integer $quantity
	 * @return void
	 */
	private function create_order_from_product($charge, $product_id, $variation_id = 0, $quantity = 1){
		// Reset any shipping settings
		WC()->shipping->reset_shipping();
		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();
		// Create a cart
		WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );

		// Set shipping on cart
		$this->set_shipping($charge);

		// Calculate cart totals
		WC()->cart->calculate_totals();

		return $this->create_order($charge);
	}

	/**
	 * create_request
	 *
	 * Creates base request arguments for Buyte API to use with wp_remote_request related functions
	 *
	 * @param string $path
	 * @param array $body
	 * @return void
	 */
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
	/**
	 * execute_request
	 *
	 * Takes a Request arg sourced from create_request to execute an network request to the Buyte API
	 *
	 * @param [type] $request
	 * @return void
	 */
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
	/**
	 * create_charge
	 *
	 * Accepts a payment token returned from widget payment authorisation.
	 * Uses request handlers to create a Buyte Charge using the Buyte API.
	 *
	 * @param object $paymentToken
	 * @return void
	 */
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

	/**
	 * is_woocommerce_active
	 *
	 * Used to determine whether or not woocommerce is active or not.
	 * Located in the root plugin file to prevent undefined/dependency issues.
	 */
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