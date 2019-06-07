<?php

class WC_Buyte_Widget{

	private $WC_Buyte;

    public function __construct(WC_Buyte $WC_Buyte)
    {
        $this->WC_Buyte = $WC_Buyte;
    }

	public function init_hooks(){
		if($this->display_product()){
			add_action('woocommerce_after_add_to_cart_button', array($this, 'render_product'), 30);
		}
		if($this->display_cart()){
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
		$options['public_key'] = $this->get_public_key();
		$options['widget_id'] = $this->get_widget_id();
		$options['options'] = array(
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
				array_push($items, array(
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
				array(
					'name' => $product->get_name(),
					'amount' => number_format($product->get_price(), 2),
				)
			);
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
		$this->render(
			$this->output_options($options),
			esc_url(plugins_url('assets/js/cart_page.js', dirname(__FILE__)))
		);
	}
	public function render_checkout(){
		$options = $this->get_cart_options();
		$this->render($this->output_options($options));
	}

	public function render($output_options = '', $page_js = '', $widget_data = array()){
		$buyte_settings = (object) $output_options;
		if(!$buyte_settings->public_key){
			return;
		}
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
		return $this->WC_Buyte->WC_Buyte_Config->get_option(WC_Buyte_Config::CONFIG_DISPLAY_CHECKOUT);
	}
	private function display_product(){
		return $this->WC_Buyte->WC_Buyte_Config->get_option(WC_Buyte_Config::CONFIG_DISPLAY_PRODUCT) === 'yes';
	}
	private function display_cart(){
		return $this->WC_Buyte->WC_Buyte_Config->get_option(WC_Buyte_Config::CONFIG_DISPLAY_CART) === 'yes';
	}
}