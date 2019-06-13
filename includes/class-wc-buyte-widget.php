<?php

defined( 'ABSPATH' ) || exit;

class WC_Buyte_Widget{

	const PROPERTY_PUBLIC_KEY = "publicKey";
	const PROPERTY_WIDGET_ID = "widgetId";
	const PROPERTY_ITEMS = "items";
	const PROPERTY_OPTIONS = "options";

	private $WC_Buyte;

    public function __construct(WC_Buyte $WC_Buyte)
    {
        $this->WC_Buyte = $WC_Buyte;
    }

	public function init_hooks(){
		// WC_Buyte_Config::log("Initiating Buyte widget WP Hooks...", WC_Buyte_Config::LOG_LEVEL_DEBUG);
		if($this->display_product()){
			// WC_Buyte_Config::log("About to render on product page...", WC_Buyte_Config::LOG_LEVEL_DEBUG);
			add_action('woocommerce_after_add_to_cart_button', array($this, 'render_product'), 10);
		}
		if($this->display_cart()){
			// WC_Buyte_Config::log("About to render on cart page...", WC_Buyte_Config::LOG_LEVEL_DEBUG);
			add_action('woocommerce_proceed_to_checkout', array($this, 'render_cart'), 20);
		}
		if($checkout_location = $this->display_checkout()){
			if($checkout_location === WC_Buyte_Config::CHECKOUT_LOCATION_BEFORE_FORM){
				add_action('woocommerce_before_checkout_form', array($this, 'render_checkout'), 20);
			}else if($checkout_location === WC_Buyte_Config::CHECKOUT_LOCATION_AFTER_FORM){
				add_action('woocommerce_review_order_after_payment', array($this, 'render_checkout'), 20);
			}
		}
	}

	public function start_options(){
		$options = array();
		$options[self::PROPERTY_PUBLIC_KEY] = $this->get_public_key();
		$options[self::PROPERTY_WIDGET_ID] = $this->get_widget_id();
		$options[self::PROPERTY_OPTIONS] = (object) array(
			'dark' => $this->is_on_dark_background(),
			'enabled' => true
		);
		return $options;
	}

	public function get_cart_options(){
		$WC_Session = WC()->session;
		$cart = $WC_Session->get('cart');
		$options = $this->start_options();

		$items = array();
		if(!empty($cart)){
			// TODO: Get taxes
			// TODO: Get discounts (coupons/discounts/etc.)
			foreach($cart as $item) {
				$product = wc_get_product($item['product_id']);
				$variation = $item['variation_id'] ? new WC_Product_Variation($item['variation_id']) : null;
				array_push($items, (object) array(
					'name' => $variation ? $variation->get_name() : $product->get_name(),
					'amount' => $this->format_price($variation ? $variation->get_price() : $product->get_price()),
					'quantity' => $item['quantity']
				));
			}
		}
		$options[self::PROPERTY_ITEMS] = $items;

		return $options;
	}

	// Consider variation id here.
	public function render_product(){
		$options = $this->start_options();
		// Disable by default for product page.
		$options[self::PROPERTY_OPTIONS]->enabled = false;

		$product = wc_get_product();
		if($product->is_purchasable()){
			$options[self::PROPERTY_ITEMS] = array(
				(object) array(
					'name' => $product->get_name(),
					'amount' => $this->format_price($product->get_price()),
				)
			);
			WC_Buyte_Config::log("Rendering on product page... \n" . print_r($options, true), WC_Buyte_Config::LOG_LEVEL_DEBUG);
			$this->render(
				$this->output_options($options),
				esc_url(plugins_url('assets/js/product_page.js', dirname(__FILE__))),
				array(
					'product_id' => $product->get_id()
				)
			);
		}
	}
	public function render_cart(){
		$options = $this->get_cart_options();
		WC_Buyte_Config::log("Rendering on cart page... \n" . print_r($options, true), WC_Buyte_Config::LOG_LEVEL_DEBUG);
		$this->render(
			$this->output_options($options)
		);
	}
	public function render_checkout(){
		$options = $this->get_cart_options();
		WC_Buyte_Config::log("Rendering on checkout page... \n" . print_r($options, true), WC_Buyte_Config::LOG_LEVEL_DEBUG);
		$this->render($this->output_options($options));
	}

	public function render($output_options = '', $page_js = '', $widget_data = array()){
		if(array_key_exists(self::PROPERTY_PUBLIC_KEY, $output_options) ? !$output_options[self::PROPERTY_PUBLIC_KEY] : true){
			WC_Buyte_Config::log("Could not render. ". self::PROPERTY_PUBLIC_KEY ." does not exist. \n" . print_r($output_options, true), WC_Buyte_Config::LOG_LEVEL_DEBUG);
			return;
		}
		$buyte_settings = json_encode($output_options);
		include plugin_dir_path( __FILE__ ) . 'view/widget/display.php';
	}

	public function output_options($options){
		return $options;
	}

	public function format_price($price) {
		return (int) ($price * 100);
	}

	public function config_invalid() {
		return !$this->WC_Buyte->WC_Buyte_Config->is_enabled() ||
			!$this->WC_Buyte->WC_Buyte_Config->get_public_key() ||
			!$this->WC_Buyte->WC_Buyte_Config->get_secret_key() ||
			!$this->WC_Buyte->WC_Buyte_Config->get_widget_id();
	}

	private function get_public_key(){
		if(!$this->WC_Buyte->WC_Buyte_Config->is_enabled()){
			return;
		}
		return $this->WC_Buyte->WC_Buyte_Config->get_public_key();
	}
	private function get_secret_key(){
		if(!$this->WC_Buyte->WC_Buyte_Config->is_enabled()){
			return;
		}
		return $this->WC_Buyte->WC_Buyte_Config->get_secret_key();
	}
	private function get_widget_id(){
		if(!$this->WC_Buyte->WC_Buyte_Config->is_enabled()){
			return;
		}
		return $this->WC_Buyte->WC_Buyte_Config->get_widget_id();
	}
	private function is_on_dark_background(){
		if(!$this->WC_Buyte->WC_Buyte_Config->is_enabled()){
			return;
		}
		return $this->WC_Buyte->WC_Buyte_Config->is_on_dark_background();
	}

	private function display_checkout(){
		if($this->config_invalid()){
			return;
		}
		return $this->WC_Buyte->WC_Buyte_Config->get_option(WC_Buyte_Config::CONFIG_DISPLAY_CHECKOUT);
	}
	private function display_product(){
		if($this->config_invalid()){
			return;
		}
		return $this->WC_Buyte->WC_Buyte_Config->get_option(WC_Buyte_Config::CONFIG_DISPLAY_PRODUCT) === 'yes';
	}
	private function display_cart(){
		if($this->config_invalid()){
			return;
		}
		return $this->WC_Buyte->WC_Buyte_Config->get_option(WC_Buyte_Config::CONFIG_DISPLAY_CART) === 'yes';
	}
}