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
		$options['publicKey'] = "";
		$options['widgetId'] = "";
		return $options;
	}
	public function get_shipping_method_options(){
		$options = array('data-shipping-required' => null);
		$shipping_methods = $this->WC_Buyte->WC_Buyte_Config->get_shipping_methods();
		if(sizeof($shipping_methods) === 1){
			$options['data-shipping-method'] = sprintf("%s, %s, %s", $shipping_methods[0]->title, $shipping_methods[0]->price, $shipping_methods[0]->desc);
		}else{
			foreach($shipping_methods as $index => $method){
				$options['data-shipping-method-' . ($index + 1)] = sprintf("%s, %s, %s", $method->title, $method->price, $method->desc);
			}
		}
		return $options;
	}

	public function get_cart_options(){
		$WC_Session = WC()->session;
		$cart = $WC_Session->get('cart');
		$options = $this->start_options();
		$total_amount = 0;

		if(!empty($cart)){
			if(sizeof($cart) === 1){
				$product = wc_get_product($cart[0]['product_id']);
				if(!$product->is_virtual() && !$product->is_downloadable()){
					$options = array_merge($options, $this->get_shipping_method_options());
				}
				$options['data-item-wc-product-name'] = $product->get_name();
				$options['data-item-label'] = $product->get_name() . ($cart[0]['quantity'] > 1 ? sprintf(' x%s', $cart[0]['quantity']) : '');
				$options['data-item-amount'] = number_format($product->get_price(), 2);
				$total_amount += $product->get_price() * ((int) $cart[0]['quantity']);
			}else{
				$options = array_merge($options, $this->get_shipping_method_options());
				$count = 1;
				foreach($cart as $item){
					$product = wc_get_product($item['product_id']);
					$options['data-item-' . $count . '-wc-product-name'] = $product->get_name();
					$options['data-item-' . $count . '-label'] = $product->get_name() . ($item['quantity'] > 1 ? sprintf(' x%s', $item['quantity']) : '');
					$options['data-item-' . $count . '-amount'] = number_format($product->get_price(), 2);
					$total_amount += $product->get_price() * ((int) $item['quantity']);
					$count++;
				}
			}
		}
		$options['data-total-amount'] = number_format($total_amount, 2);

		return $options;
	}

	// Consider variation id here.
	public function render_product(){
		$product = wc_get_product();
		if($product->is_purchasable()){
			$options = $this->start_options();
			if(!$product->is_virtual() && !$product->is_downloadable()){
				$options = array_merge($options, $this->get_shipping_method_options());
			}
			$options['data-item-wc-product-name'] = $product->get_name();
			$options['data-item-label'] = $product->get_name();
			$options['data-item-amount'] = number_format($product->get_price(), 2);
			$options['data-total-amount'] = number_format($product->get_price(), 2);
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