<?php

/**
 * Plugin Name:       Buyte
 * Plugin URI:        https://wordpress.org/plugins/buyte-woocommerce-plugin/
 * Description:       Offer your customers Apple Pay and Google Pay in a single install. By integrating Buyte into your e-commerce website, your visitors can securely checkout with their mobile wallet.
 * Version:           0.2.5
 * Author:            Web Doodle
 * Author URI:        https://www.webdoodle.com.au/
 * License:           GPL-2.0+
 * Github URI:        https://github.com/rsoury/buyte-woocommerce
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 *
 *
 * @version  0.2.5
 * @package  Buyte
 * @author   Buyte
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

if (!WC_Buyte::is_woocommerce_active()) {
	return;
}

class WC_Buyte
{
	/* version number */
	const VERSION = '0.2.5';
	/* ajax */
	const AJAX_SUCCESS = 'buyte_success';
	const AJAX_GET_SHIPPING = 'buyte_shipping';
	const AJAX_PRODUCT_TO_CART = 'buyte_product_to_cart';
	const AJAX_PRODUCT_TO_CART_WITH_SHIPPING = 'buyte_product_to_cart_with_shipping';

	/** @var \WC_Buyte single instance of this plugin */
	protected static $instance;

	public $WC_Buyte_Config;
	public $WC_Buyte_Widget;


	public function __construct()
	{
		add_action('plugins_loaded', array($this, 'initialize'), 100);
	}

	public function initialize()
	{
		$this->load_dependencies();

		// The earlier we handle these, the better. This way we have access to our public vars.
		// Handle Settings Tab
		$this->handle_config();
		// Handle Widget loads
		$this->handle_widget();

		// Setup plugin action links -- see plugin page.
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));

		// Setup admin ajax endpoints
		add_action('wp_ajax_' . self::AJAX_SUCCESS, array($this, 'ajax_buyte_success'));
		add_action('wp_ajax_nopriv_' . self::AJAX_SUCCESS, array($this, 'ajax_buyte_success'));
		add_action('wp_ajax_' . self::AJAX_GET_SHIPPING, array($this, 'ajax_buyte_shipping'));
		add_action('wp_ajax_nopriv_'  . self::AJAX_GET_SHIPPING, array($this, 'ajax_buyte_shipping'));
		add_action('wp_ajax_' . self::AJAX_PRODUCT_TO_CART, array($this, 'ajax_buyte_product_to_cart'));
		add_action('wp_ajax_nopriv_' . self::AJAX_PRODUCT_TO_CART, array($this, 'ajax_buyte_product_to_cart'));
		add_action('wp_ajax_' . self::AJAX_PRODUCT_TO_CART_WITH_SHIPPING, array($this, 'ajax_buyte_product_to_cart_with_shipping'));
		add_action('wp_ajax_nopriv_' . self::AJAX_PRODUCT_TO_CART_WITH_SHIPPING, array($this, 'ajax_buyte_product_to_cart_with_shipping'));

		// Handle Payment Gateway
		add_filter('woocommerce_payment_gateways', array($this, 'handle_payment_gateway'));
		add_filter('woocommerce_available_payment_gateways', array($this, 'gateway_availability'));

		// Add order meta data after order process
		add_action('woocommerce_checkout_order_processed', array($this, 'add_order_meta'), 10, 1);
	}

	public function basename()
	{
		return plugin_basename(__FILE__);
	}

	public static function instance()
	{
		if (is_null(self::$instance)) {
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
	public function ajax_buyte_success()
	{
		try {
			WC_Buyte_Config::log("buyte_success: Processing Buyte checkout...", WC_Buyte_Config::LOG_LEVEL_INFO);
			// Retrieve JSON payload
			$posted = json_decode(file_get_contents('php://input'));
			if (!$posted) {
				$posted = json_decode(json_encode($_POST));
			}

			// Validate
			if (!property_exists($posted, 'paymentToken')) {
				throw new Exception("Payment Token not provided.");
			} else if (empty($posted->paymentToken)) {
				throw new Exception("Payment Token is empty.");
			}

			// Get charge
			$charge = $this->create_charge($posted->paymentToken);
			WC_Buyte_Config::log("buyte_success: Charge created.", WC_Buyte_Config::LOG_LEVEL_INFO);
			if (property_exists($charge, 'id')) {
				// Order functions use a WC checkout method that sends a redirect url to the frontend for us.
				$this->create_order($charge);
				WC_Buyte_Config::log("buyte_success: Order created and confirmation url sent.", WC_Buyte_Config::LOG_LEVEL_INFO);
				exit;
			} else {
				WC_Buyte_Config::log("buyte_success: Charge does not have Id. Contact Buyte Support.", WC_Buyte_Config::LOG_LEVEL_WARN);
			}
		} catch (Exception $e) {
			WC_Buyte_Config::log("buyte_success: Error", WC_Buyte_Config::LOG_LEVEL_ERROR);
			WC_Buyte_Config::log($e, WC_Buyte_Config::LOG_LEVEL_ERROR);
		}
		wp_send_json_error(array(  // send JSON back
			'result' => 'checkout_failed',
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
	public function ajax_buyte_product_to_cart()
	{
		WC_Buyte_Config::log("buyte_product_to_cart: Converting product to cart...", WC_Buyte_Config::LOG_LEVEL_INFO);
		$response = array();

		try {
			// Retrieve JSON payload
			$posted = json_decode(file_get_contents('php://input'));
			if (!$posted) {
				$posted = json_decode(json_encode($_POST));
			}

			WC_Buyte_Config::log($posted, WC_Buyte_Config::LOG_LEVEL_DEBUG);

			$product_id = property_exists($posted, "productId") ? $posted->productId : 0;
			$quantity = property_exists($posted, "quantity") ? $posted->quantity : 1;
			$variation_id = property_exists($posted, "variationId") ? $posted->variationId : 0;

			// Convert Product to Cart
			$to_cart_response = $this->convert_product_to_cart($product_id, $quantity, $variation_id);

			$response['result'] = 'success';

			$response = array_merge($response, $to_cart_response);

			wp_send_json($response);
		} catch (Exception $e) {
			WC_Buyte_Config::log("buyte_product_to_cart: Error", WC_Buyte_Config::LOG_LEVEL_ERROR);
			WC_Buyte_Config::log($e, WC_Buyte_Config::LOG_LEVEL_ERROR);

			$response['result'] = 'cannot_convert_product_to_cart';

			wp_send_json($response);
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
	public function ajax_buyte_product_to_cart_with_shipping()
	{
		WC_Buyte_Config::log("buyte_product_to_cart_with_shipping: Converting product to cart...", WC_Buyte_Config::LOG_LEVEL_INFO);
		$response = array();

		try {
			// Retrieve JSON payload
			$posted = json_decode(file_get_contents('php://input'));
			if (!$posted) {
				$posted = json_decode(json_encode($_POST));
			}

			WC_Buyte_Config::log($posted, WC_Buyte_Config::LOG_LEVEL_DEBUG);

			$product_id = property_exists($posted, "productId") ? $posted->productId : 0;
			$quantity = property_exists($posted, "quantity") ? $posted->quantity : 1;
			$variation_id = property_exists($posted, "variationId") ? $posted->variationId : 0;

			// Convert Product to Cart
			$to_cart_response = $this->convert_product_to_cart($product_id, $quantity, $variation_id);

			WC_Buyte_Config::log("buyte_product_to_cart_with_shipping: Successfully converted product to cart. Getting shipping from cart...", WC_Buyte_Config::LOG_LEVEL_INFO);

			// Get shipping
			$shipping_response = $this->get_shipping_from_cart($posted);

			WC_Buyte_Config::log("buyte_product_to_cart_with_shipping: Successfully retrieved shipping response", WC_Buyte_Config::LOG_LEVEL_INFO);

			$response['result'] = 'success';

			$response = array_merge($response, $shipping_response, $to_cart_response);

			wp_send_json($response);
		} catch (Exception $e) {
			WC_Buyte_Config::log("buyte_product_to_cart_with_shipping: Error", WC_Buyte_Config::LOG_LEVEL_ERROR);
			WC_Buyte_Config::log($e, WC_Buyte_Config::LOG_LEVEL_ERROR);

			$response['result'] = 'failed_product_to_cart_with_shipping';

			wp_send_json($response);
		}
	}

	/**
	 * ajax_buyte_shipping
	 *
	 * Accepts shipping address data and responds with shipping rates for the current cart.
	 *
	 * @return void
	 */
	public function ajax_buyte_shipping()
	{
		WC_Buyte_Config::log("buyte_shipping: Getting shipping response...", WC_Buyte_Config::LOG_LEVEL_INFO);
		$response = array();

		try {
			// Retrieve JSON payload
			$posted = json_decode(file_get_contents('php://input'));
			if (!$posted) {
				$posted = json_decode(json_encode($_POST));
			}

			WC_Buyte_Config::log($posted, WC_Buyte_Config::LOG_LEVEL_DEBUG);

			$shipping_response = $this->get_shipping_from_cart($posted);

			WC_Buyte_Config::log("buyte_shipping: Successfully retrieved shipping response", WC_Buyte_Config::LOG_LEVEL_INFO);

			$response['result'] = 'success';

			$response = array_merge($response, $shipping_response);

			wp_send_json($response);
		} catch (Exception $e) {
			WC_Buyte_Config::log("buyte_shipping: Error", WC_Buyte_Config::LOG_LEVEL_ERROR);
			WC_Buyte_Config::log($e, WC_Buyte_Config::LOG_LEVEL_ERROR);

			$response['result'] = 'invalid_shipping_address';

			wp_send_json($response);
		}
	}

	/**
	 * Load all dependency files for this plugin.
	 *
	 */
	public function load_dependencies()
	{
		require_once plugin_dir_path(__FILE__) . 'includes/class-wc-buyte-config.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-wc-buyte-widget.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-wc-buyte-util.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-wc-buyte-payment-gateway.php';
	}

	/**
	 * Adds plugin action links.
	 *
	 */
	public function plugin_action_links($links)
	{
		$plugin_links = array(
			'<a href="admin.php?page=wc-settings&tab=' . $this->WC_Buyte_Config->id . '">' . esc_html__('Settings', 'woocommerce') . '</a>',
			'<a href="' . $this->WC_Buyte_Config->settings_website . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Contact Us', 'woocommerce') . '</a>'
		);
		return array_merge($plugin_links, $links);
	}

	/**
	 * Setup and Initiate Config
	 *
	 * @return void
	 */
	public function handle_config()
	{
		$this->WC_Buyte_Config = new WC_Buyte_Config($this);
		$this->WC_Buyte_Config->init();
	}
	/**
	 * Setup and initiate the Buyte widget
	 *
	 * @return void
	 */
	public function handle_widget()
	{
		$this->WC_Buyte_Widget = new WC_Buyte_Widget($this);
		$this->WC_Buyte_Widget->init_hooks();
	}
	/**
	 * Setup and initiate Buyte as a means of processing order payments. -- payment gateway.
	 *
	 * @param array[string] $methods
	 * @return void
	 */
	public function handle_payment_gateway($methods)
	{
		$methods[] = 'WC_Buyte_Payment_Gateway';
		return $methods;
	}
	/**
	 * gateway_availability
	 *
	 * Ensures Buyte Gateway only available when order params inclure a Buyte Charge Id.
	 *
	 * @param array $gateways
	 * @return void
	 */
	public function gateway_availability($gateways)
	{
		$id = WC_Buyte_Config::get_id();
		if (!isset($gateways[$id])) {
			return $gateways;
		}

		if (!$this->is_buyte_checkout()) {
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
	public function is_buyte_checkout()
	{
		if (isset($_POST['buyte_charge'])) {
			return !empty($_POST['buyte_charge']);
		}
		return false;
	}

	/**
	 * add_order_meta
	 *
	 * Adds Buyte related metadata to the order
	 *
	 * @param integer $order_id
	 * @return void
	 */
	public function add_order_meta($order_id)
	{
		if (!$this->is_buyte_checkout()) {
			return;
		}

		$order = wc_get_order($order_id);
		$charge_id = sanitize_text_field($_POST['buyte_charge']);
		$payment_source_id = sanitize_text_field($_POST['buyte_payment_source']);
		$payment_type = sanitize_text_field($_POST['buyte_payment_type']);
		$provider_name = sanitize_text_field($_POST['buyte_provider_name']);
		$provider_reference = sanitize_text_field($_POST['buyte_provider_reference']);

		// $method_title = $payment_type . ' ('. $this->WC_Buyte_Config->label .')';
		$method_title = $payment_type;
		if (WC_Buyte_Util::is_wc_lt('3.0')) {
			update_post_meta($order_id, '_payment_method_title', $method_title);
		} else {
			$order->set_payment_method_title($method_title);
			$order->save();
		}

		update_post_meta($order_id, '_buyte_charge_id', $charge_id);
		update_post_meta($order_id, '_buyte_payment_source_id', $payment_source_id);

		// Set Provider details
		update_post_meta($order_id, '_buyte_provider_name', $provider_name);
		update_post_meta($order_id, '_buyte_provider_reference', $provider_reference);
	}

	/**
	 * convert_product_to_cart
	 *
	 * Convert the single product into a cart where shipping/tax/fees can be calculated by Woocommerce.
	 *
	 * @param integer $product_id
	 * @param integer $qty
	 * @param integer $variation_id
	 * @return void
	 */
	public function convert_product_to_cart($product_id, $qty = 1, $variation_id = 0)
	{
		if (empty($product_id)) {
			throw new Exception("Product ID not provided");
		}

		WC()->shipping->reset_shipping();

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		if (empty($variation_id)) {
			WC()->cart->add_to_cart($product_id, $qty);
		} else {
			WC()->cart->add_to_cart($product_id, $qty, $variation_id);
		}

		// Calculate totals
		WC()->cart->calculate_totals();

		// return updated items
		// tax, discounts, fees, etc.

		$data = array();
		$items = array();

		// Add taxes from cart
		$tax = WC_Buyte_Util::get_cart_tax();
		if (!empty($tax)) {
			$items[] = (object) array(
				'name' => __("Tax", 'woocommerce'),
				'amount' => $tax,
				'type' => 'tax'
			);
		}

		// Add discount from cart
		$discount = WC_Buyte_Util::get_cart_discount();
		if (!empty($discount)) {
			$items[] = (object) array(
				'name' => __("Discount", 'woocommerce'),
				'amount' => $discount,
				'type' => 'discount'
			);
		}

		// Include fees and taxes as display items.
		$cart_fees = 0;
		if (WC_Buyte_Util::is_wc_lt('3.2')) {
			$cart_fees = WC()->cart->fees;
		} else {
			$cart_fees = WC()->cart->get_fees();
		}
		foreach ($cart_fees as $key => $fee) {
			$amount = WC_Buyte_Util::get_amount($fee->amount);
			if (!empty($amount)) {
				$items[] = (object) array(
					'name' => $fee->name,
					'amount' => $amount,
					'type' => 'tax'
				);
			}
		}

		$data['items'] = $items;

		if (!defined('BUYTE_CART')) {
			define('BUYTE_CART', true);
		}

		return $data;
	}

	/**
	 * get_shipping_from_cart
	 *
	 * Get shipping methods from current cart.
	 * Depending on the items and the customer address, shipping options may change.
	 *
	 * @param object $posted
	 * @return array
	 */
	public function get_shipping_from_cart($posted)
	{
		$this->calculate_shipping($posted);

		// Set the shipping options.
		$data     = array();
		$packages = WC()->shipping->get_packages();

		if (!empty($packages) && WC()->customer->has_calculated_shipping()) {
			foreach ($packages as $package_key => $package) {
				if (empty($package['rates'])) {
					throw new Exception(__('Unable to find shipping method for address.', 'woocommerce'));
				}

				foreach ($package['rates'] as $key => $rate) {
					$data['shippingMethods'][] = array(
						'id'     => $rate->id,
						'label'  => $rate->label,
						'description' => '',
						'rate' => WC_Buyte_Util::get_amount($rate->cost),
					);
				}
			}
		} else {
			throw new Exception(__('Unable to find shipping method for address.', 'woocommerce'));
		}

		if (isset($data[0])) {
			// Auto select the first shipping method.
			WC()->session->set('chosen_shipping_methods', array($data[0]['id']));
		}

		WC()->cart->calculate_totals();

		return $data;
	}

	/**
	 * Calculate and set shipping method.
	 */
	protected function calculate_shipping($address)
	{
		$country   = sanitize_text_field($address->country);
		$state     = sanitize_text_field($address->state);
		$postcode  = sanitize_text_field($address->postcode);
		$city      = sanitize_text_field($address->city);
		$address_1 = sanitize_text_field($address->address);
		$address_2 = sanitize_text_field($address->address_2);

		$wc_states = WC()->countries->get_states($country);

		/**
		 * In some versions of Chrome, state can be a full name. So we need
		 * to convert that to abbreviation as WC is expecting that.
		 */
		if (2 < strlen($state) && !empty($wc_states)) {
			$state = array_search(ucwords(strtolower($state)), $wc_states, true);
		}

		WC()->shipping->reset_shipping();

		if ($postcode && WC_Validation::is_postcode($postcode, $country)) {
			$postcode = wc_format_postcode($postcode, $country);
		}

		if ($country) {
			WC()->customer->set_location($country, $state, $postcode, $city);
			WC()->customer->set_shipping_location($country, $state, $postcode, $city);
		} else {
			WC_Buyte_Util::is_wc_lt('3.0') ? WC()->customer->set_to_base() : WC()->customer->set_billing_address_to_base();
			WC_Buyte_Util::is_wc_lt('3.0') ? WC()->customer->set_shipping_to_base() : WC()->customer->set_shipping_address_to_base();
		}

		if (WC_Buyte_Util::is_wc_lt('3.0')) {
			WC()->customer->calculated_shipping(true);
		} else {
			WC()->customer->set_calculated_shipping(true);
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

		foreach (WC()->cart->get_cart() as $item) {
			if ($item['data']->needs_shipping()) {
				if (isset($item['line_total'])) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		$packages = apply_filters('woocommerce_cart_shipping_packages', $packages);

		WC_Buyte_Config::log($packages, WC_Buyte_Config::LOG_LEVEL_DEBUG);

		WC()->shipping->calculate_shipping($packages);
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
	protected function create_order($charge)
	{
		// Cart is already set here.
		if (WC()->cart->is_empty()) {
			$errMsg = "Empty cart";
			WC_Buyte_Config::log($errMsg, WC_Buyte_Config::LOG_LEVEL_ERROR);
			throw new Exception($errMsg);
		}

		if (!property_exists($charge, 'id')) {
			$errMsg = "No Buyte charge Id";
			WC_Buyte_Config::log($errMsg, WC_Buyte_Config::LOG_LEVEL_ERROR);
			throw new Exception($errMsg);
		}

		if (!property_exists($charge, 'customer')) {
			$errMsg = "No customer information in Buyte charge";
			WC_Buyte_Config::log($errMsg, WC_Buyte_Config::LOG_LEVEL_ERROR);
			throw new Exception($errMsg);
		}

		$shipping_method = null;
		if (isset($charge->source->shippingMethod)) {
			$this->update_shipping_method($charge->source->shippingMethod);
			$shipping_method = $charge->source->shippingMethod->id;
		}

		$customer = $charge->customer;

		// Customer Name
		$first_name = '';
		if (property_exists($customer, 'givenName')) {
			$first_name = $customer->givenName;
		}
		$last_name = '';
		if (property_exists($customer, 'familyName')) {
			$last_name = $customer->familyName;
		}
		if (property_exists($customer, 'name')) {
			$split_name = WC_Buyte_Util::split_name($customer->name);
			if (empty($first_name)) {
				$first_name = sanitize_text_field($split_name[0]);
			}
			if (empty($last_name)) {
				$last_name = sanitize_text_field($split_name[1]);
			}
		}

		$postdata = array(
			'shipping_first_name' => $first_name,
			'shipping_last_name' => $last_name,
			'billing_first_name' => $first_name,
			'billing_last_name' => $last_name,
			'shipping_company' => '',
			'shipping_country' =>
			sanitize_text_field(
				isset($customer->shippingAddress->countryCode) ?
					$customer->shippingAddress->countryCode : (isset($customer->shippingAddress->country) ? $customer->shippingAddress->country : '')
			),
			'shipping_address_1' =>
			sanitize_text_field(
				isset($customer->shippingAddress->addressLines) ?
					(sizeof($customer->shippingAddress->addressLines) > 0 ? $customer->shippingAddress->addressLines[0] : '') :
					''
			),
			'shipping_address_2' =>
			sanitize_text_field(
				isset($customer->shippingAddress->addressLines) ?
					(sizeof($customer->shippingAddress->addressLines) > 1 ? $customer->shippingAddress->addressLines[1] : '') :
					''
			),
			'shipping_city' => sanitize_text_field(isset($customer->shippingAddress->locality) ? $customer->shippingAddress->locality : ''),
			'shipping_state' => sanitize_text_field(isset($customer->shippingAddress->administrativeArea) ? $customer->shippingAddress->administrativeArea : ''),
			'shipping_postcode' => sanitize_text_field(isset($customer->shippingAddress->postalCode) ? $customer->shippingAddress->postalCode : ''),
		);
		if (isset($customer->billingAddress) ? !empty((array) $customer->billingAddress) : false) {
			$postdata += array(
				'billing_company' => '',
				'billing_country' =>
				sanitize_text_field(
					isset($customer->billingAddress->countryCode) ?
						$customer->billingAddress->countryCode : (isset($customer->billingAddress->country) ? $customer->billingAddress->country : '')
				),
				'billing_address_1' =>
				sanitize_text_field(
					isset($customer->billingAddress->addressLines) ?
						(sizeof($customer->billingAddress->addressLines) > 0 ? $customer->billingAddress->addressLines[0] : '') :
						''
				),
				'billing_address_2' =>
				sanitize_text_field(
					isset($customer->billingAddress->addressLines) ?
						(sizeof($customer->billingAddress->addressLines) > 1 ? $customer->billingAddress->addressLines[1] : '') :
						''
				),
				'billing_city' => sanitize_text_field(isset($customer->billingAddress->locality) ? $customer->billingAddress->locality : ''),
				'billing_state' => sanitize_text_field(isset($customer->billingAddress->administrativeArea) ? $customer->billingAddress->administrativeArea : ''),
				'billing_postcode' => sanitize_text_field(isset($customer->billingAddress->postalCode) ? $customer->billingAddress->postalCode : ''),
			);
		} else {
			$postdata += array(
				'billing_company' => sanitize_text_field($postdata['shipping_company']),
				'billing_country' => sanitize_text_field($postdata['shipping_country']),
				'billing_address_1' => sanitize_text_field($postdata['shipping_address_1']),
				'billing_address_2' => sanitize_text_field($postdata['shipping_address_2']),
				'billing_city' => sanitize_text_field($postdata['shipping_city']),
				'billing_state' => sanitize_text_field($postdata['shipping_state']),
				'billing_postcode' => sanitize_text_field($postdata['shipping_postcode'])
			);
		}

		// Comments
		$comments = "Checkout completed with Buyte";
		$payment_type = '';
		if (isset($charge->source->paymentMethod->name)) {
			$payment_type = $charge->source->paymentMethod->name;
			$comments .= "'s " . $charge->source->paymentMethod->name . ".";
		}
		if (isset($charge->source->shippingMethod)) {
			$shipping_method_name = sanitize_text_field(isset($charge->source->shippingMethod->label) ? $charge->source->shippingMethod->label : '');
			$shipping_method_description = sanitize_textarea_field(isset($charge->source->shippingMethod->description) ? $charge->source->shippingMethod->description : '');
			$shipping_method_rate = isset($charge->source->shippingMethod->rate) ? intval($charge->source->shippingMethod->rate) : 0;
		}

		// Recreate $_POST for checkout
		$postdata += array(
			'billing_email' => property_exists($customer, 'emailAddress') ? sanitize_email($customer->emailAddress) : null,
			'billing_phone' => property_exists($customer, 'phoneNumber') ? wc_sanitize_phone_number($customer->phoneNumber) : null,
			'shipping_method' => $shipping_method,
			'order_comments' => sanitize_textarea_field($comments),
			'payment_method' => $this->WC_Buyte_Config->id,
			'ship_to_different_address' => 1,
			'terms' => 1,
			'buyte_charge' => $charge->id,
			'buyte_payment_source' => $charge->source->id,
			'buyte_payment_type' => $payment_type,
		);

		if (isset($charge->providerCharge->reference)) {
			$postdata += array(
				'buyte_provider_name' => ucfirst(strtolower($charge->providerCharge->type)),
				'buyte_provider_reference' => $charge->providerCharge->reference,
			);
		}

		WC_Buyte_Config::log("create_order: Post data set", WC_Buyte_Config::LOG_LEVEL_INFO);
		WC_Buyte_Config::log($postdata, WC_Buyte_Config::LOG_LEVEL_DEBUG);

		// Required to process checkout using WC
		$_REQUEST['woocommerce-process-checkout-nonce'] = wp_create_nonce('woocommerce-process_checkout');
		$_POST = $postdata;

		// Execute WC Proceed Checkout on the existing cart.

		if (!defined('BUYTE_CHECKOUT')) {
			define('BUYTE_CHECKOUT', true);
		}

		WC()->checkout()->process_checkout();

		die(0);
	}

	/**
	 * update_shipping_method
	 *
	 * Update the selected shipping method.
	 *
	 * @return void
	 */

	/**
	 * update_shipping_method
	 *
	 * Update the selected shipping method.
	 *
	 * @param mixed $shipping_method
	 * @return void
	 */
	protected function update_shipping_method($shipping_method)
	{
		$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

		if (is_object($shipping_method)) {
			$i = 0;
			$vars = get_object_vars($shipping_method);
			foreach ($vars as $key => $value) {
				$chosen_shipping_methods[$i] = wc_clean($value);
				$i++;
			}
		} else if (is_array($shipping_method)) {
			foreach ($shipping_method as $i => $value) {
				$chosen_shipping_methods[$i] = wc_clean($value);
			}
		} else {
			$chosen_shipping_methods = array($shipping_method);
		}

		WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);

		WC()->cart->calculate_totals();
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
	protected function create_request($path, $body)
	{
		$data = json_encode($body);
		if (!$data) {
			throw new Exception('Cannot encode Buyte request body.');
		}
		$baseUrl = $this->WC_Buyte_Config->get_api_endpoint();
		$url = $baseUrl . $path;
		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $this->WC_Buyte_Config->get_secret_key(),
			'Client-Name' => 'Woocommerce',
			'Client-Version' => WC_Buyte_Util::get_wc_version()
		);
		$args = array(
			'headers' => $headers,
			'body' => $data,
			'timeout' => 45,
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
	 * @param array $request
	 * @return void
	 */
	protected function execute_request($request)
	{
		$url = $request['url'];
		$args = $request['args'];
		$response = wp_remote_post($url, $args);
		if (is_wp_error($response)) {
			WC_Buyte_Config::log("execute_request: Error", WC_Buyte_Config::LOG_LEVEL_FATAL);
			WC_Buyte_Config::log($response, WC_Buyte_Config::LOG_LEVEL_FATAL);
			return;
		}
		WC_Buyte_Config::log("execute_request: Successful", WC_Buyte_Config::LOG_LEVEL_DEBUG);
		WC_Buyte_Config::log($response, WC_Buyte_Config::LOG_LEVEL_DEBUG);
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
	protected function create_charge($paymentToken)
	{
		$request = $this->create_request('charges', array(
			'source' => $paymentToken->id,
			'amount' => (int) $paymentToken->amount,
			'currency' => $paymentToken->currency
		));
		WC_Buyte_Config::log("create_charge: Attempting to charge Buyte Payment Token: " . $paymentToken->id, WC_Buyte_Config::LOG_LEVEL_INFO);
		WC_Buyte_Config::log($request, WC_Buyte_Config::LOG_LEVEL_DEBUG);
		$response = $this->execute_request($request);
		if (empty($response) ? true : !property_exists($response, 'id')) {
			WC_Buyte_Config::log("create_charge: Could not create charge", WC_Buyte_Config::LOG_LEVEL_FATAL);
			WC_Buyte_Config::log(json_encode($response), WC_Buyte_Config::LOG_LEVEL_FATAL);
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
	public static function is_woocommerce_active()
	{
		$active_plugins = (array) get_option('active_plugins', array());

		if (is_multisite()) {
			$active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
		}

		return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
	}
}

function WC_Buyte()
{
	return WC_Buyte::instance();
}

$GLOBALS['WC_Buyte'] = WC_Buyte();
