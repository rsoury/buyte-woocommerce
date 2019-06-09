<?php

defined( 'ABSPATH' ) || exit;

class WC_Buyte_Widget{

	private $WC_Buyte;

    public function __construct(WC_Buyte $WC_Buyte)
    {
        $this->WC_Buyte = $WC_Buyte;
    }

	public function init_hooks(){
		WC_Buyte_Config::log("Initiating Buyte widget WP Hooks...", WC_Buyte_Config::LOG_LEVEL_DEBUG);
		if($this->display_product()){
			WC_Buyte_Config::log("About to render on product page...", WC_Buyte_Config::LOG_LEVEL_DEBUG);
			add_action('woocommerce_after_add_to_cart_button', array($this, 'render_product'), 10);
		}
		if($this->display_cart()){
			WC_Buyte_Config::log("About to render on cart page...", WC_Buyte_Config::LOG_LEVEL_DEBUG);
			add_action('woocommerce_after_cart', array($this, 'render_cart'), 20);
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
		$options['publicKey'] = $this->get_public_key();
		$options['widgetId'] = $this->get_widget_id();
		$options['options'] = (object) array(
			'dark' => $this->is_on_dark_background()
		);
		return $options;
	}

	public function get_cart_options(){
		$WC_Session = WC()->session;
		$cart = $WC_Session->get('cart');
		$options = $this->start_options();

		$items = array();
		if(!$cart->is_empty()){
			// TODO: Get taxes
			// TODO: Get discounts (coupons/discounts/etc.)
			foreach($cart as $item) {
				$product = wc_get_product($item['product_id']);
				array_push($items, (object) array(
					'name' => $product->get_name(),
					'amount' => number_format($product->get_price(), 2),
					'quantity' => $item['quantity']
				));
			}
		}
		$options['items'] = $items;

		return $options;
	}

	// Consider variation id here.
	public function render_product(){
		$options = $this->start_options();
		$product = wc_get_product();
		if($product->is_purchasable()){
			$options['items'] = array(
				(object) array(
					'name' => $product->get_name(),
					'amount' => number_format($product->get_price(), 2),
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
			$this->output_options($options),
			esc_url(plugins_url('assets/js/cart_page.js', dirname(__FILE__)))
		);
	}
	public function render_checkout(){
		$options = $this->get_cart_options();
		WC_Buyte_Config::log("Rendering on checkout page... \n" . print_r($options, true), WC_Buyte_Config::LOG_LEVEL_DEBUG);
		$this->render($this->output_options($options));
	}

	public function render($output_options = '', $page_js = '', $widget_data = array()){
		if(!$output_options['public_key']){
			return;
		}
		$buyte_settings = json_encode($output_options);
		include plugin_dir_path( __FILE__ ) . '/view/widget/display.php';
	}

	public function output_options($options){
		return $options;
	}

	public function get_redirect_url($product_id = ''){
		return '/?p=buyte&route=payment&action_type=success' . ($product_id ? '&product_id=' . $product_id : '');
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
		if(!$this->WC_Buyte->WC_Buyte_Config->is_enabled()){
			return;
		}
		return $this->WC_Buyte->WC_Buyte_Config->get_option(WC_Buyte_Config::CONFIG_DISPLAY_CHECKOUT);
	}
	private function display_product(){
		if(!$this->WC_Buyte->WC_Buyte_Config->is_enabled()){
			return;
		}
		return $this->WC_Buyte->WC_Buyte_Config->get_option(WC_Buyte_Config::CONFIG_DISPLAY_PRODUCT) === 'yes';
	}
	private function display_cart(){
		if(!$this->WC_Buyte->WC_Buyte_Config->is_enabled()){
			return;
		}
		return $this->WC_Buyte->WC_Buyte_Config->get_option(WC_Buyte_Config::CONFIG_DISPLAY_CART) === 'yes';
	}
}