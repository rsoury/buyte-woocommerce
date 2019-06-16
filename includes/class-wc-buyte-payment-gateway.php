<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_Buyte_Payment_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id             = WC_Buyte_Config::get_id();
        $this->method_title   = __( WC_Buyte_Config::get_label(), 'woocommerce' );
        $this->has_fields = false;
        $this->method_description = __( WC_Buyte_Config::get_description(), 'woocommerce' );
        // Get data from settings page.
        $this->enabled = WC_Admin_Settings::get_option(WC_Buyte_Config::CONFIG_ENABLED);
    }

    /**
    * Output the gateway settings screen.
    */
    public function admin_options() {
        wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=' . strtolower( $this->id ) ) );
        parent::admin_options();
    }

    /**
     * process_payment
     * Process the order payment.
     *
     * @param [int] $order_id
     * @return void
     */
    public function process_payment( $order_id ){
        // Check if order id has buyte charge id stored against it.
        // If so, then return success.
        // Else return failure.
        WC_Buyte_Config::log("process_payment: Processing payment in Buyte Payment Gateway class.", WC_Buyte_Config::LOG_LEVEL_INFO);

        $order = wc_get_order($order_id);

        $charge_id = get_post_meta( $order_id, '_buyte_charge_id', true );

        if(empty($charge_id)){
            WC_Buyte_Config::log("process_payment: Failed to find Buyte charge id.", WC_Buyte_Config::LOG_LEVEL_WARN);
            return array();
        }

        WC_Buyte_Config::log("process_payment: Successfully verified Buyte order payment.", WC_Buyte_Config::LOG_LEVEL_INFO);
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order )
        );
    }
}