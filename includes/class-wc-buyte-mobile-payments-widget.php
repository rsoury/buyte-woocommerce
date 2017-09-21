<?php

class WC_Buyte_Mobile_Payments_Widget{

	private $WC_Buyte_Mobile_Payments;

    public function __construct(WC_Buyte_Mobile_Payments $WC_Buyte_Mobile_Payments)
    {
        $this->WC_Buyte_Mobile_Payments = $WC_Buyte_Mobile_Payments;
    }

	public function init_hooks(){
		

	}

	public function render(){
		include plugin_dir_path( __FILE__ ) . '/view/widget/script.php';
	}

	public function output_widget_options(){
		echo "";

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