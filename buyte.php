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
		add_action( 'wp_ajax_buyte_success', array( $this, 'buyte_success' ) );
		add_action( 'wp_ajax_nopriv_buyte_success', array( $this, 'buyte_success' ) );
		add_action( 'wp_ajax_buyte_cart', array( $this, 'buyte_cart' ) );
		add_action( 'wp_ajax_nopriv_buyte_cart', array( $this, 'buyte_cart' ) );

		// Handle Settings Tab
		$this->handle_config();

		// Handle Widget loads
		$this->handle_widget();
	}

	public function buyte_success() {
		// check nonce
		// $nonce = isset($_POST['nextNonce']) ? $_POST['nextNonce'] : "";
		// if ( ! wp_verify_nonce( $nonce, self::NONCE_NAME ) ) {
		// 	header("HTTP/1.1 401 Unauthorized");
   		// 	exit;
		// }

		// Retrieve HTTP method
		// $method = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING);
		// Retrieve JSON payload
		$data = json_decode(file_get_contents('php://input'));
		if(!$data){
			$data = json_decode(json_encode($_POST));
		}
		$charge = $this->create_charge($data->paymentToken);

		wp_send_json(array(  // send JSON back
			'charge' => $charge
		));
		exit;
	}

	public function buyte_cart() {
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
			'Client-Version' => $this->get_wc_version()
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
			$this->WC_Buyte_Config->log("Error on Request Execute", WC_Buyte_Config::LOG_LEVEL_FATAL);
			$this->WC_Buyte_Config->log($response, WC_Buyte_Config::LOG_LEVEL_FATAL);
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
		$this->WC_Buyte_Config->log("Attempting to charge Buyte Payment Token: " . $paymentToken->id, WC_Buyte_Config::LOG_LEVEL_INFO);
		$this->WC_Buyte_Config->log($request, WC_Buyte_Config::LOG_LEVEL_DEBUG);
		$response = $this->execute_request($request);
		if(empty($response)){
			$this->WC_Buyte_Config->log("Could not create charge", WC_Buyte_Config::LOG_LEVEL_FATAL);
			throw new Exception("Could not create Charge");
		}
		$this->WC_Buyte_Config->log("Successfully created charge: " . $response->id, WC_Buyte_Config::LOG_LEVEL_INFO);
		$this->WC_Buyte_Config->log($response, WC_Buyte_Config::LOG_LEVEL_DEBUG);
		return $response;
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