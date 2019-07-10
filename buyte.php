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
	const AJAX_GET_SHIPPING = 'buyte_shipping';
	const AJAX_PRODUCT_TO_CART = 'buyte_product_to_cart';
	const AJAX_PRODUCT_TO_CART_WITH_SHIPPING = 'buyte_product_to_cart_with_shipping';
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

		// The earlier we handle these, the better. This way we have access to our public vars.
		// Handle Settings Tab
		$this->handle_config();
		// Handle Widget loads
		$this->handle_widget();

		// Setup plugin action links -- see plugin page.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

		// Setup admin ajax endpoints
		add_action( 'wp_ajax_buyte_success', array( $this, 'ajax_buyte_success' ) );
		add_action( 'wp_ajax_nopriv_buyte_success', array( $this, 'ajax_buyte_success' ) );
		add_action( 'wp_ajax_buyte_shipping', array( $this, 'ajax_buyte_shipping' ) );
		add_action( 'wp_ajax_nopriv_buyte_shipping', array( $this, 'ajax_buyte_shipping' ) );
		add_action( 'wp_ajax_buyte_product_to_cart', array( $this, 'ajax_buyte_product_to_cart' ) );
		add_action( 'wp_ajax_nopriv_buyte_product_to_cart', array( $this, 'ajax_buyte_product_to_cart' ) );
		add_action( 'wp_ajax_buyte_product_to_cart_with_shipping', array( $this, 'ajax_buyte_product_to_cart_with_shipping' ) );
		add_action( 'wp_ajax_nopriv_buyte_product_to_cart_with_shipping', array( $this, 'ajax_buyte_product_to_cart_with_shipping' ) );

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
			if($price > $data->paymentToken->amount){
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

	 /**
	  * ajax_buyte_product_to_cart
	  *
	  * Ajax function handler that accepts product data and creates new cart out of it.
	  *
	  * @return void
	  */
	public function ajax_buyte_product_to_cart() {
		WC_Buyte_Config::log("buyte_product_to_cart: Converting product to cart...", WC_Buyte_Config::LOG_LEVEL_INFO);
		$response = array();

		try {
			// Retrieve JSON payload
			$posted = json_decode(file_get_contents('php://input'));
			if(!$posted){
				$posted = json_decode(json_encode($_POST));
			}

			// check nonce
			if ( ! wp_verify_nonce( $posted->nextNonce, self::NONCE_NAME ) ) {
				header("HTTP/1.1 401 Unauthorized");
				exit;
			}

			WC_Buyte_Config::log("buyte_product_to_cart: Nonce verified.", WC_Buyte_Config::LOG_LEVEL_INFO);

			WC_Buyte_Util::debug_log( $posted );

			$product_id = property_exists($posted, "productId") ? $posted->productId : 0;
			$quantity = property_exists($posted, "quantity") ? $posted->quantity : 1;
			$variation_id = property_exists($posted, "variationId") ? $posted->variationId : 0;

			// Convert Product to Cart
			$this->convert_product_to_cart( $product_id, $quantity, $variation_id );

			$response['result'] = 'success';

			wp_send_json( $response );
		} catch ( Exception $e ) {
			WC_Buyte_Util::debug_log($e);

			$response['result'] = 'cannot_convert_product_to_cart';

			wp_send_json( $data );
		}
	}

	/**
	 * ajax_buyte_product_to_cart_with_shipping
	 *
	 * Ajax function handler that accepts product data and shipping address data.
	 * Converts product to new cart and responds with shipping rates for the new cart.
	 *
	 * @return void
	 */
	public function ajax_buyte_product_to_cart_with_shipping() {
		WC_Buyte_Config::log("buyte_product_to_cart_with_shipping: Converting product to cart...", WC_Buyte_Config::LOG_LEVEL_INFO);
		$response = array();

		try {
			// Retrieve JSON payload
			$posted = json_decode(file_get_contents('php://input'));
			if(!$posted){
				$posted = json_decode(json_encode($_POST));
			}

			// check nonce
			// if ( ! wp_verify_nonce( $posted->nextNonce, self::NONCE_NAME ) ) {
			// 	header("HTTP/1.1 401 Unauthorized");
			// 	exit;
			// }

			// WC_Buyte_Config::log("buyte_product_to_cart_with_shipping: Nonce verified.", WC_Buyte_Config::LOG_LEVEL_INFO);

			WC_Buyte_Util::debug_log( $posted );

			$product_id = property_exists($posted, "productId") ? $posted->productId : 0;
			$quantity = property_exists($posted, "quantity") ? $posted->quantity : 1;
			$variation_id = property_exists($posted, "variationId") ? $posted->variationId : 0;

			// Convert Product to Cart
			$this->convert_product_to_cart( $product_id, $quantity, $variation_id );

			WC_Buyte_Config::log("buyte_product_to_cart_with_shipping: Converted product to cart. Getting shipping from cart...", WC_Buyte_Config::LOG_LEVEL_INFO);

			// Get shipping
			$shippingResponse = $this->get_shipping_from_cart( $posted );

			WC_Buyte_Config::log("buyte_product_to_cart_with_shipping: Successfully retrieved shipping response", WC_Buyte_Config::LOG_LEVEL_INFO);	

			$response['result'] = 'success';

			$response = array_merge($response, $shippingResponse);

			wp_send_json( $response );
		} catch ( Exception $e ) {
			WC_Buyte_Util::debug_log($e);

			$response['result'] = 'failed_product_to_cart_with_shipping';

			wp_send_json( $data );
		}
	}

	/**
	 * ajax_buyte_shipping
	 *
	 * Accepts shipping address data and responds with shipping rates for the current cart.
	 *
	 * @return void
	 */
	public function ajax_buyte_shipping() {
		WC_Buyte_Config::log("buyte_shipping: Getting shipping response...", WC_Buyte_Config::LOG_LEVEL_INFO);
		$response = array();

		try {
			// Retrieve JSON payload
			$posted = json_decode(file_get_contents('php://input'));
			if(!$posted){
				$posted = json_decode(json_encode($_POST));
			}

			// check nonce
			// if ( ! wp_verify_nonce( $posted->nextNonce, self::NONCE_NAME ) ) {
			// 	header("HTTP/1.1 401 Unauthorized");
			// 	exit;
			// }

			// WC_Buyte_Config::log("buyte_shipping: Nonce verified.", WC_Buyte_Config::LOG_LEVEL_INFO);

			WC_Buyte_Util::debug_log( $posted );

			$data = $this->get_shipping_from_cart( $posted );

			WC_Buyte_Config::log("buyte_shipping: Successfully retrieved shipping response", WC_Buyte_Config::LOG_LEVEL_INFO);

			$data['result'] = 'success';

			wp_send_json( $data );
		} catch ( Exception $e ) {
			WC_Buyte_Util::debug_log($e);

			$data['result'] = 'invalid_shipping_address';

			wp_send_json( $data );
		}
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
     * Adds plugin action links.
     *
     */
    public function plugin_action_links( $links ) {
        $plugin_links = array(
            '<a href="admin.php?page=wc-settings&tab='. $this->WC_Buyte_Config->id .'">' . esc_html__( 'Settings', 'woocommerce' ) . '</a>',
            '<a href="'. $this->WC_Buyte_Config->settings_website .'" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Website', 'woocommerce' ) . '</a>'
        );
        return array_merge( $plugin_links, $links );
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
		$provider_name = $_POST['buyte_provider_name'];
		$provider_reference = $_POST['buyte_provider_reference'];
		$shipping_name = $_POST['buyte_shipping_name'];
		$shipping_description = $_POST['buyte_shipping_description'];
		$shipping_rate = $_POST['buyte_shipping_rate'];

		// $method_title = $payment_type . ' ('. $this->WC_Buyte_Config->label .')';
		$method_title = $payment_type;
		if ( WC_Buyte_Util::is_wc_lt( '3.0' ) ) {
			update_post_meta( $order_id, '_payment_method_title', $method_title );
		} else {
			$order->set_payment_method_title( $method_title );
			$order->save();
		}

		update_post_meta( $order_id, '_buyte_charge_id', $charge_id );
		update_post_meta( $order_id, '_buyte_payment_source_id', $payment_source_id );

		// Set Provider details
		update_post_meta( $order_id, '_buyte_provider_name', $provider_name );
		update_post_meta( $order_id, '_buyte_provider_reference', $provider_reference );

		// Set shipping information
		update_post_meta( $order_id, '_buyte_shipping_name', $shipping_name );
		update_post_meta( $order_id, '_buyte_shipping_description', $shipping_description );
		update_post_meta( $order_id, '_buyte_shipping_rate', $shipping_rate );
		// Set order note
		$order->add_order_note("
			Shipping Method:
				Name: <strong>". $shipping_name ."</strong>
				Description: <strong>". $shipping_description ."</strong>
				Rate: <strong>". WC_Buyte_Util::get_price( $shipping_rate, true, true ) ."</strong>
		");
		// Set order shipping totals post order creation.
		if(!empty( $shipping_rate )){
			$shipping_total = WC_Buyte_Util::get_price( $shipping_rate );
			$total = $order->get_total() + $shipping_total;
			$order->set_shipping_total( $shipping_total );
			$order->set_total( $total );
			$order->save();
		}
	}

	/**
	 * convert_product_to_cart
	 *
	 * Convert the single product into a cart where shipping/tax/fees can be calculated by Woocommerce.
	 *
	 * @param [int] $product_id
	 * @param integer $qty
	 * @param integer $variation_id
	 * @return void
	 */
	public function convert_product_to_cart( $product_id, $qty = 1, $variation_id = 0 ) {
		if( empty($product_id) ){
			throw new Exception("Product ID not provided");
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		if (empty( $variation_id )) {
			WC()->cart->add_to_cart( $product->get_id(), $qty );
		} else {
			WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id );
		}

		WC()->cart->calculate_totals();
	}

	/**
	 * get_shipping_from_cart
	 *
	 * Get shipping methods from current cart.
	 * Depending on the items and the customer address, shipping options may change.
	 *
	 * @param [object] $posted
	 * @return array
	 */
	public function get_shipping_from_cart( $posted ){
		$this->calculate_shipping( $posted );

		// Set the shipping options.
		$data     = array();
		$packages = WC()->shipping->get_packages();

		if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
			foreach ( $packages as $package_key => $package ) {
				if ( empty( $package['rates'] ) ) {
					throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce' ) );
				}

				foreach ( $package['rates'] as $key => $rate ) {
					$data['shippingMethods'][] = array(
						'id'     => $rate->id,
						'label'  => $rate->label,
						'description' => '',
						'rate' => WC_Buyte_Util::get_amount( $rate->cost ),
					);
				}
			}
		} else {
			throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce' ) );
		}

		if ( isset( $data[0] ) ) {
			// Auto select the first shipping method.
			WC()->session->set( 'chosen_shipping_methods', array( $data[0]['id'] ) );
		}

		WC()->cart->calculate_totals();

		return $data;
	}

	/**
	 * Calculate and set shipping method.
	 */
	protected function calculate_shipping( $address ) {
		$country   = $address->country;
		$state     = $address->state;
		$postcode  = $address->postcode;
		$city      = $address->city;
		$address_1 = $address->address;
		$address_2 = $address->address_2;
		$wc_states = WC()->countries->get_states( $country );

		/**
		 * In some versions of Chrome, state can be a full name. So we need
		 * to convert that to abbreviation as WC is expecting that.
		 */
		if ( 2 < strlen( $state ) && ! empty( $wc_states ) ) {
			$state = array_search( ucwords( strtolower( $state ) ), $wc_states, true );
		}

		WC()->shipping->reset_shipping();

		if ( $postcode && WC_Validation::is_postcode( $postcode, $country ) ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC_Buyte_Util::is_wc_lt( '3.0' ) ? WC()->customer->set_to_base() : WC()->customer->set_billing_address_to_base();
			WC_Buyte_Util::is_wc_lt( '3.0' ) ? WC()->customer->set_shipping_to_base() : WC()->customer->set_shipping_address_to_base();
		}

		if ( WC_Buyte_Util::is_wc_lt( '3.0' ) ) {
			WC()->customer->calculated_shipping( true );
		} else {
			WC()->customer->set_calculated_shipping( true );
			WC()->customer->save();
		}

		$packages = array();

		$packages[0]['contents']                 = WC()->cart->get_cart();
		$packages[0]['contents_cost']            = 0;
		$packages[0]['applied_coupons']          = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']               = get_current_user_id();
		$packages[0]['destination']['country']   = $country;
		$packages[0]['destination']['state']     = $state;
		$packages[0]['destination']['postcode']  = $postcode;
		$packages[0]['destination']['city']      = $city;
		$packages[0]['destination']['address']   = $address_1;
		$packages[0]['destination']['address_2'] = $address_2;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $item['data']->needs_shipping() ) {
				if ( isset( $item['line_total'] ) ) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		$packages = apply_filters( 'woocommerce_cart_shipping_packages', $packages );

		WC()->shipping->calculate_shipping( $packages );
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
	protected function create_order($charge){
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
		$shipping_postdata = array();
		if(isset($charge->source->paymentMethod->name)){
			$payment_type = $charge->source->paymentMethod->name;
			$comments .= "'s " . $charge->source->paymentMethod->name . ".";
		}
		if(isset($charge->source->shippingMethod)){
			$shipping_method_name = isset($charge->source->shippingMethod->label) ? $charge->source->shippingMethod->label : '';
			$shipping_method_description = isset($charge->source->shippingMethod->description) ? $charge->source->shippingMethod->description : '';
			$shipping_method_rate = isset($charge->source->shippingMethod->rate) ? $charge->source->shippingMethod->rate : 0;
			$shipping_postdata = array(
				'buyte_shipping_name' => $shipping_method_name,
				'buyte_shipping_description' => $shipping_method_description,
				'buyte_shipping_rate' => $shipping_method_rate
			);
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
		);

		if(isset( $charge->providerCharge->reference )){
			$postdata += array(
				'buyte_provider_name' => ucfirst(strtolower($charge->providerCharge->type)),
				'buyte_provider_reference' => $charge->providerCharge->reference,
			);
		}

		if(!empty($shipping_postdata)){
			$postdata += $shipping_postdata;
		}

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
	 * create_order_from_cart
	 *
	 * Uses existing cart to process checkout and create order
	 *
	 * @param object $charge
	 * @return void
	 */
	protected function create_order_from_cart($charge){
		// Cart is already set here.
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'woocommerce' ) );
			exit;
		}

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
	protected function create_order_from_product($charge, $product_id, $variation_id = 0, $quantity = 1){
		// Reset any shipping settings
		WC()->shipping->reset_shipping();
		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();
		// Create a cart
		WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
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
	protected function create_request($path, $body){
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
	protected function execute_request($request) {
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
	 * @param object|stdClass $paymentToken
	 * @return void
	 */
	protected function create_charge($paymentToken){
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
