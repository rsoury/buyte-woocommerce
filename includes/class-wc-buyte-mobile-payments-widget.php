<?php

class WC_Buyte_Mobile_Payments_Widget{

	private $WC_Buyte_Mobile_Payments;

    public function __construct(WC_Buyte_Mobile_Payments $WC_Buyte_Mobile_Payments)
    {
        $this->WC_Buyte_Mobile_Payments = $WC_Buyte_Mobile_Payments;
    }

	public function init_hooks(){
		if($this->display_product()){
			add_action('woocommerce_after_add_to_cart_button', array($this, 'render_product'), 30);
		}
		if($this->display_cart()){
			add_action('woocommerce_proceed_to_checkout', array($this, 'render_cart'), 20);
		}
		if($this->display_checkout()){
			add_action('woocommerce_proceed_to_checkout', array($this, 'render_checkout'));
		}
	}

	public function start_options(){
		$options = array();
		$options['data-country'] = wc_get_base_location()['country'];
		$options['data-currency'] = get_woocommerce_currency();
		$options['data-total-text'] = get_bloginfo('name');
		return $options;
	}
	public function get_shipping_method_options(){
		$options = array('data-shipping-required' => null);
		
		return $options;
	}
	public function render_product(){
		$product = wc_get_product();
		if($product->is_purchasable()){
			$options = $this->start_options();
			if(!$product->is_virtual()){
				$options = array_merge($options, $this->get_shipping_method_options());
			}
			$options['data-item'] = $product->get_name() . ', ' . number_format($product->get_price(), 2);
			$options['data-total-amount'] = number_format($product->get_price(), 2);
			$this->render($this->output_options($options));
		}
	}
	public function render_cart(){
		$WC_Session = WC()->session;
		$this->render();
	}
	public function render_checkout(){
		$WC_Session = WC()->session;
		$this->render();
	}

	public function render($output_options = ''){
		$public_key = $this->get_public_key();
		if(!$public_key){
			return;
		}
		include plugin_dir_path( __FILE__ ) . '/view/widget/display.php';
	}

	public function output_options($options){
		$output = '';
		foreach($options as $key => $value){
			$output .= $key . ($value !== null ? '="' . $value . '"' : '') . ' '; 
		}
		return $output;

		/*
		data-country="AU"
		data-currency="AUD"

		data-total-text="This awesome product"
		data-total-amount="95.99"

		data-shipping-method-1-label="Express Shipping"
		data-shipping-method-1-amount="5.555"
		data-shipping-method-1-detail="Delivers in 2 business days"

		data-shipping-method-2-label="Free Shipping"
		data-shipping-method-2-amount="0.00"
		data-shipping-method-2-detail="Delivers in 7 business days"

		data-shipping-method-3="Cheap Shipping, 2.50, Delivers in 3-5 business days"

		data-item-1-label="Cool Portion"
		data-item-1-amount="40.00"

		data-item-2="Other Portion, 55.99"
		 */
	}

	private function get_public_key(){
		if(!$this->WC_Buyte_Mobile_Payments->WC_Buyte_Mobile_Payments_Config->isEnabled()){
			return;
		}
		return $this->WC_Buyte_Mobile_Payments->WC_Buyte_Mobile_Payments_Config->get_public_key();
	}

	private function display_checkout(){
		return $this->WC_Buyte_Mobile_Payments->WC_Buyte_Mobile_Payments_Config->get_option(WC_Buyte_Mobile_Payments_Config::CONFIG_DISPLAY_CHECKOUT) === 'yes';
	}
	private function display_product(){
		return $this->WC_Buyte_Mobile_Payments->WC_Buyte_Mobile_Payments_Config->get_option(WC_Buyte_Mobile_Payments_Config::CONFIG_DISPLAY_PRODUCT) === 'yes';
	}
	private function display_cart(){
		return $this->WC_Buyte_Mobile_Payments->WC_Buyte_Mobile_Payments_Config->get_option(WC_Buyte_Mobile_Payments_Config::CONFIG_DISPLAY_CART) === 'yes';
	}
}