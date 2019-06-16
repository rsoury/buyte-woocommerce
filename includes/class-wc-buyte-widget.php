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
			add_action('woocommerce_proceed_to_checkout', array($this, 'render_cart'), 10);
		}
		if($checkout_location = $this->display_checkout()){
			if($checkout_location === WC_Buyte_Config::CHECKOUT_LOCATION_BEFORE_FORM){
				add_action('woocommerce_checkout_before_customer_details', array($this, 'render_checkout'), 10);
			}else if($checkout_location === WC_Buyte_Config::CHECKOUT_LOCATION_AFTER_FORM){
				add_action('woocommerce_review_order_after_payment', array($this, 'render_checkout'), 10);
			}
		}
	}


	/**
	 * Abstracts logic to get base options/settings for widget settings
	 */
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

	/**
	 * get_cart_options
	 *
	 * Abstracts logic to get all cart data for the widget for both cart and checkout page widget rendering.
	 *
	 * @return void
	 */
	public function get_cart_options(){
		$WC_Session = WC()->session;
		$cart = $WC_Session->get('cart');
		$options = $this->start_options();

		$items = array();

		// Add products from cart
		if(!empty($cart)){
			foreach($cart as $item) {
				$item_name = "";
				$item_amount = 0;
				if( array_key_exists('variation_id', $item) ? !empty($item['variation_id']) : false ){
					$variation = new WC_Product_Variation($item['variation_id']);
					$item_name = WC_Buyte_Util::is_wc_lt( '3.0' ) ? $variation->name : $variation->get_name();
					$item_amount = WC_Buyte_Util::get_amount(
						WC_Buyte_Util::is_wc_lt( '3.0' ) ? $variation->price : $variation->get_price()
					);
				}else{
					$product = wc_get_product($item['product_id']);
					$item_name = WC_Buyte_Util::is_wc_lt( '3.0' ) ? $product->name : $product->get_name();
					$item_amount = WC_Buyte_Util::get_amount(
						WC_Buyte_Util::is_wc_lt( '3.0' ) ? $product->price : $product->get_price()
					);
				}
				array_push($items, (object) array(
					'name' => $item_name,
					'amount' => $item_amount,
					'quantity' => $item['quantity']
				));
			}
		}

		// Add taxes from cart
		if( wc_tax_enabled() ){
			$tax = wc_format_decimal( WC()->cart->tax_total, WC()->cart->dp );
			$amount = WC_Buyte_Util::get_amount( $tax );
			if(!empty( $amount )){
				$items[] = (object) array(
					'name' => __( "Tax", 'woocommerce' ),
					'amount' => $amount,
					'type' => 'tax'
				);
			}
		}

		// Add discounts from cart
		if ( WC()->cart->has_discount() ) {
			$discounts = 0;
			if ( WC_Buyte_Util::is_wc_lt( '3.2' ) ) {
				$discounts = wc_format_decimal( WC()->cart->get_cart_discount_total(), WC()->cart->dp );
			} else {
				$applied_coupons = array_values( WC()->cart->get_coupon_discount_totals() );
				foreach ( $applied_coupons as $amount ) {
					$discounts += (float) $amount;
				}
			}
			$discounts = wc_format_decimal( $discounts, WC()->cart->dp );
			$amount = WC_Buyte_Util::get_amount( $discounts );
			if(!empty( $amount )){
				$items[] = (object) array(
					'name' => __( "Discount", 'woocommerce' ),
					'amount' => $amount,
					'type' => 'discount'
				);
			}
		}

		// Include fees and taxes as display items.
		$cart_fees = 0;
		if ( WC_Buyte_Util::is_wc_lt( '3.2' ) ) {
			$cart_fees = WC()->cart->fees;
		} else {
			$cart_fees = WC()->cart->get_fees();
		}
		foreach ( $cart_fees as $key => $fee ) {
			$amount = WC_Buyte_Util::get_amount( $fee->amount );
			if(!empty( $amount )){
				$items[] = (object) array(
					'name' => $fee->name,
					'amount' => $amount,
					'type' => 'tax'
				);
			}
		}

		$options[self::PROPERTY_ITEMS] = $items;

		return $options;
	}

	/**
	 * render_product
	 *
	 * Function to collect product data and render the widget on the product page
	 *
	 *
	 * @return void
	 */
	public function render_product(){
		$options = $this->start_options();
		// Disable by default for product page.
		$options[self::PROPERTY_OPTIONS]->enabled = false;

		$product = wc_get_product();
		if( !$product->is_purchasable() ){
			return;
		}

		$product_item = array(
			(object) array(
				'name' => WC_Buyte_Util::is_wc_lt( '3.0' ) ? $product->name : $product->get_name(),
				'amount' => WC_Buyte_Util::get_amount(
					WC_Buyte_Util::is_wc_lt( '3.0' ) ? $product->price : $product->get_price()
				),
			)
		);

		$options[self::PROPERTY_ITEMS] = $product_item;

		WC_Buyte_Config::log("Rendering on product page... \n" . print_r($options, true), WC_Buyte_Config::LOG_LEVEL_DEBUG);
		$this->render(
			$this->output_options($options),
			esc_url(plugins_url('assets/js/product_page.js', dirname(__FILE__))),
			array(
				'product_id' => $product->get_id()
			)
		);
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