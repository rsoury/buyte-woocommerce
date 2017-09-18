<?php

class WC_Buyte_Mobile_Payments_Config{

	// Admin Setting Keys
	const CONFIG_ENABLED = 'enabled';
	const CONFIG_PUBLIC_KEY = 'public_key';
	const CONFIG_LOGGING_LEVEL = 'log_level';
	const CONFIG_DISPLAY_CHECKOUT = 'display_checkout';
	const CONFIG_DISPLAY_CART = 'display_cart';
	const CONFIG_DISPLAY_PRODUCT = 'display_product';

	//Log levels
    const LOG_LEVEL_ALL = 1;
    const LOG_LEVEL_DEBUG = 2;
    const LOG_LEVEL_INFO = 3;
    const LOG_LEVEL_WARN = 4;
    const LOG_LEVEL_ERROR = 5;
    const LOG_LEVEL_FATAL = 6;
    const LOG_LEVEL_OFF = 7;

	public $WC_Buyte_Mobile_Payments;

	public function __construct(WC_Buyte_Mobile_Payments $WC_Buyte_Mobile_Payments){
		$this->WC_Buyte_Mobile_Payments = $WC_Buyte_Mobile_Payments;
	}

	public function init(){
		add_filter( 'woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50 );
        add_action( 'woocommerce_settings_tabs_settings_tab_buyte', array($this, 'settings_tab') );
        add_action( 'woocommerce_update_options_settings_tab_buyte', array($this, 'update_settings') );
	}

	public function settings_tab(){
		woocommerce_admin_fields($this->get_settings());
	}
	public function get_settings(){
		$settings = array(
			self::CONFIG_ENABLED => array(
				'title' => __('Enable/Disable', 'woocommerce'),
                'label' => __('Enable ZipMoney Payment', 'woocommerce'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
			),
			self::CONFIG_PUBLIC_KEY => array(
				'title' => __('Public Key', 'woocommerce'),
                'type' => 'text',
                'desc_tip' => __('Get your Public Key from Buyte - https://buyte.co/', 'woocommerce'),
                'default' => ''
			),
			self::CONFIG_LOGGING_LEVEL => array(
				'title' => __('Log Message level', 'woocommerce'),
                'description' => __('The log level will be used to log the messages. The orders are: ALL < DEBUG < INFO < WARN < ERROR < FATAL < OFF.'),
                'type' => 'select',
                'default' => self::LOG_LEVEL_ALL,
                'options' => array(
                    self::LOG_LEVEL_ALL => 'All messages',
                    self::LOG_LEVEL_DEBUG => 'Debug (and above)',
                    self::LOG_LEVEL_INFO => 'Info (and above)',
                    self::LOG_LEVEL_WARN => 'Warn (and above)',
                    self::LOG_LEVEL_ERROR => 'Error (and above)',
                    self::LOG_LEVEL_FATAL => 'Fatal (and above)',
                    self::LOG_LEVEL_OFF => 'Off (No message will be logged)'
                )
			),
			self::CONFIG_DISPLAY_CHECKOUT => array(
                'title' => __('Display on Checkout Page', 'woocommerce'),
                'type' => 'checkbox',
                'desc_tip' => __('Enables the display of Buyte\'s Apple Pay Widget on the Checkout Page', 'woocommerce'),
                'default' => 'yes'
			),
			self::CONFIG_DISPLAY_CART => array(
                'title' => __('Display on Cart Page', 'woocommerce'),
                'type' => 'checkbox',
                'desc_tip' => __('Enables the display of Buyte\'s Apple Pay Widget on the Cart Page', 'woocommerce'),
                'default' => 'yes'
			),
			self::CONFIG_DISPLAY_PRODUCT => array(
                'title' => __('Display on Product Page', 'woocommerce'),
                'type' => 'checkbox',
                'desc_tip' => __('Enables the display of Buyte\'s Apple Pay Widget on the Product Page', 'woocommerce'),
                'default' => 'yes'
			)
		);

		return apply_filters( 'wc_settings_tab_buyte_settings', $settings );
	}

	public function add_settings_tab( $settings_tabs ) {
        $settings_tabs['settings_tab_buyte'] = 'Buyte';
        return $settings_tabs;
    }
}