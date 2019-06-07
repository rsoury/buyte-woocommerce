<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Buyte_Config', false ) ) {
	return new WC_Buyte_Config();
}

class WC_Buyte_Config extends WC_Settings_Page{

	// Admin Setting Keys
	const CONFIG_ENABLED = 'enabled';
	const CONFIG_SECRET_KEY = 'secret_key';
	const CONFIG_PUBLIC_KEY = 'public_key';
	const CONFIG_WIDGET_ID = 'widget_id';
	const CONFIG_DARK_BACKGROUND = 'dark_background';
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

    // Checkout Page Locations
    const CHECKOUT_LOCATION_BEFORE_FORM = 'checkout_location_before_form';
    const CHECKOUT_LOCATION_AFTER_FORM = 'checkout_location_after_form';
    const CHECKOUT_LOCATION_OFF = 'checkout_location_off';


	public $WC_Buyte;

    protected $id = 'buyte';
    protected $label = 'Buyte';
    public $settings_description = 'Offer your customers Apple Pay and Google Pay through a widget that sits on your website. By integrating Buyte into your e-commerce website, your visitors can securely checkout with their mobile wallet.';
    public $settings_webite = 'https://www.buytecheckout.com/';

    public static $config_log_level = self::LOG_LEVEL_ALL;
    public static $logger;

	public function __construct(WC_Buyte $WC_Buyte){
		$this->WC_Buyte = $WC_Buyte;
	}

	public function init(){
        add_filter( 'woocommerce_get_settings_pages', array($this, 'add_settings_page'), 10, 2 );
        // Plugin settings link
        // add_filter( 'plugin_action_links_' . $this->WC_Buyte->basename(), array($this, 'plugin_settings_link') );
	}

    public function add_settings_page( $settings ) {
        array_push($settings, $this);
        return $settings;
    }

	public function get_settings(){
		$settings = array(
			self::CONFIG_ENABLED => array(
				'title' => __('Enable/Disable', 'woocommerce'),
                'label' => __('Enable Buyte Checkout', 'woocommerce'),
                'type' => 'checkbox',
                'desc' => '',
                'default' => 'no'
			),
			self::CONFIG_WIDGET_ID => array(
				'title' => __('Checkout Widget ID', 'woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('Get your Checkout Widget ID from <a href="%s" target="_blank">Buyte</a>', 'woocommerce'), $this->settings_webite),
                'default' => ''
			),
			self::CONFIG_PUBLIC_KEY => array(
				'title' => __('Public Key', 'woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('Get your Public Key from <a href="%s" target="_blank">Buyte</a>', 'woocommerce'), $this->settings_webite),
                'default' => ''
			),
			self::CONFIG_SECRET_KEY => array(
				'title' => __('Secret Key', 'woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('Get your Secret Key from <a href="%s" target="_blank">Buyte</a>', 'woocommerce'), $this->settings_webite),
                'default' => ''
            ),
            self::CONFIG_DARK_BACKGROUND => array(
                'title' => __('Is On Dark Background?', 'woocommerce'),
                'type' => 'checkbox',
                'desc' => __('Set true if background is dark. Will render light digital wallet buttons.', 'woocommerce'),
                'default' => 'no'
            ),
			self::CONFIG_LOGGING_LEVEL => array(
				'title' => __('Log Message level', 'woocommerce'),
                'desc' => __('The log level will be used to log the messages. The orders are: ALL < DEBUG < INFO < WARN < ERROR < FATAL < OFF.'),
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
                'type' => 'select',
                'desc_tip' => __('Enables the display of Buyte\'s Apple Pay Widget on the Checkout Page', 'woocommerce'),
                'default' => self::CHECKOUT_LOCATION_BEFORE_FORM,
                'options' => array(
                    self::CHECKOUT_LOCATION_BEFORE_FORM => 'Before Checkout Form',
                    self::CHECKOUT_LOCATION_AFTER_FORM => 'After Checkout Form',
                    self::CHECKOUT_LOCATION_OFF => 'Off (Will not display on checkout page)'
                )
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
            ),
        );
        
        $settings = apply_filters( 'woocommerce_buyte_settings', $settings );

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );;
    }
    
    /**
	 * Output the settings.
	 */
	public function output() {
		$settings = $this->get_settings();
		WC_Admin_Settings::output_fields( $settings );
	}
	/**
	 * Save settings.
	 */
	public function save() {
        $settings = $this->get_settings();
        self::$config_log_level = $this->get_option(self::CONFIG_LOGGING_LEVEL);
		WC_Admin_Settings::save_fields( $settings );
	}

    public function get_public_key(){
        return $this->get_option(self::CONFIG_PUBLIC_KEY);
    }
    public function get_secret_key(){
        return $this->get_option(self::CONFIG_SECRET_KEY);
    }
    public function get_widget_id(){
        return $this->get_option(self::CONFIG_WIDGET_ID);
    }
    public function is_on_dark_background(){
        return $this->get_option(self::CONFIG_DARK_BACKGROUND) === 'yes';
    }
    public function is_enabled(){
        return $this->get_option(self::CONFIG_ENABLED) === 'yes';
    }

    public function plugin_settings_link($links){
        $settings_url = "admin.php?page=wc-settings&tab=settings_tab_buyte";
        $settings_link = sprintf(__('<a href="%s">Settings</a>', 'woocommerce'), $settings_url); 
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Log the message when necessary
     *
     * @param $message
     * @param int $log_level
     */
    public static function log($message, $log_level = self::LOG_LEVEL_ALL)
    {
        if (self::$config_log_level > $log_level) {
            //log the message with log_level higher than the default value only
            return;
        }

        if (is_array($message) || is_object($message)) {
            //if the input is array or object, use print_r to convert it to string
            $message = print_r($message, true);
        }

        if (is_null(self::$logger)) {
            //check the logger is initialised
            self::$logger = new WC_Logger();
        }

        //log the message into file
        self::$logger->add('buyte-checkout-woocommerce-plugin', $message);
    }
}