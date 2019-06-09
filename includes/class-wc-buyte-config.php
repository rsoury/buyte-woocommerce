<?php

defined( 'ABSPATH' ) || exit;

class WC_Buyte_Config {

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

    public $id = 'buyte';
    public $label = 'Buyte';
    public $settings_description = 'Offer your customers Apple Pay and Google Pay through a widget that sits on your website. By integrating Buyte into your e-commerce website, your visitors can securely checkout with their mobile wallet.';
    public $settings_webite = 'https://www.buytecheckout.com/';
    public $settings_dashboard = 'https://dashboard.buytecheckout.com/';

    public static $config_log_level = self::LOG_LEVEL_ALL;
    public static $logger;

    public function __construct(WC_Buyte $WC_Buyte){
		$this->WC_Buyte = $WC_Buyte;
    }

    public function init(){
        add_filter( 'woocommerce_get_settings_pages', array($this, 'init_settings_page'), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
    }

    public function init_settings_page( $settings ) {
		include plugin_dir_path( __FILE__ ) . 'class-wc-buyte-settings.php';
        $settings[] = new WC_Buyte_Settings($this->WC_Buyte);
        return $settings;
    }

    public function load_admin_scripts() {
        $admin_css_url = plugin_dir_url( __FILE__ ) . '../assets/css/admin.css';
        wp_register_style( 'buyte-admin-style', $admin_css_url );
		wp_enqueue_style( 'buyte-admin-style' );
	}

	public function get_settings(){
		$settings = array(
            array(
                'id'    => $this->id . '_title',
                'title' => __( $this->label . ' settings', 'woocommerce' ),
                'type'  => 'title',
                'desc' => sprintf(__('<a href="%s" target="_blank" rel="noopener noreferrer">Don\'t have your Buyte account and credentials?</a>', 'woocommerce'), $this->settings_webite)
            ),
			 array(
                'id' => self::CONFIG_ENABLED,
				'title' => __('Enable/Disable', 'woocommerce'),
                'label' => __('Enable Buyte Checkout', 'woocommerce'),
                'type' => 'checkbox',
                'desc' => '',
                'default' => 'no'
			),
			 array(
                'id' => self::CONFIG_WIDGET_ID,
				'title' => __('Checkout Widget ID', 'woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('Can be obtained by created a Buyte Checkout in the <a href="%s" target="_blank" rel="noopener noreferrer">Buyte Dashboard</a>', 'woocommerce'), $this->settings_dashboard),
                'default' => ''
			),
			array(
                'id' => self::CONFIG_PUBLIC_KEY,
				'title' => __('Public Key', 'woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('Get your Public Key in the <a href="%s" target="_blank" rel="noopener noreferrer">Buyte Dashboard</a>', 'woocommerce'), $this->settings_dashboard),
                'default' => ''
			),
			array(
                'id' => self::CONFIG_SECRET_KEY,
				'title' => __('Secret Key', 'woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('Get your Public Key in the <a href="%s" target="_blank" rel="noopener noreferrer">Buyte Dashboard</a>', 'woocommerce'), $this->settings_dashboard),
                'default' => ''
            ),
            array(
                'id' => self::CONFIG_DARK_BACKGROUND,
                'title' => __('Is On Dark Background?', 'woocommerce'),
                'type' => 'checkbox',
                'desc' => __('Set true if background is dark. Will render light digital wallet buttons.', 'woocommerce'),
                'default' => 'no'
            ),
			array(
                'id' => self::CONFIG_DISPLAY_CHECKOUT,
                'title' => __('Display on Checkout Page', 'woocommerce'),
                'type' => 'select',
                'desc' => __('Enables the display of Buyte\'s Checkout Widget on the Checkout Page', 'woocommerce'),
                'default' => self::CHECKOUT_LOCATION_BEFORE_FORM,
                'options' => array(
                    self::CHECKOUT_LOCATION_BEFORE_FORM => 'Before Checkout Form',
                    self::CHECKOUT_LOCATION_AFTER_FORM => 'After Checkout Form',
                    self::CHECKOUT_LOCATION_OFF => 'Off (Will not display on checkout page)'
                )
			),
		    array(
                'id' => self::CONFIG_DISPLAY_CART,
                'title' => __('Display on Cart Page', 'woocommerce'),
                'type' => 'checkbox',
                'desc_tip' => __('Enables the display of Buyte\'s Checkout Widget on the Cart Page', 'woocommerce'),
                'default' => 'yes'
			),
			array(
                'id' => self::CONFIG_DISPLAY_PRODUCT,
                'title' => __('Display on Product Page', 'woocommerce'),
                'type' => 'checkbox',
                'desc_tip' => __('Enables the display of Buyte\'s Checkout Widget on the Product Page', 'woocommerce'),
                'default' => 'yes'
            ),
            array(
                'id' => self::CONFIG_LOGGING_LEVEL,
				'title' => __('Log Message level', 'woocommerce'),
                'desc' => __('The log level will be used to log the messages. The orders are: ALL < DEBUG < INFO < WARN < ERROR < FATAL < OFF.'),
                'type' => 'select',
                'default' => self::LOG_LEVEL_INFO,
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
            array(
                'id'   => $this->id . '_settings',
                'type' => 'sectionend',
            ),
        );

        return $settings;
    }

    /**
     * A hook called when settings page saves
     *
     */
    public function save() {
        self::$config_log_level = $this->get_option(self::CONFIG_LOGGING_LEVEL);
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

    public function get_option( $option_name, $default = '' ) {
        return WC_Admin_Settings::get_option($option_name, $default);
    }

    /**
     * Log the message when necessary
     *
     * @param $message
     * @param int $log_level
     */
    public static function log($message, $log_level = self::LOG_LEVEL_ALL)
    {
        if(self::$config_log_level !== self::LOG_LEVEL_ALL){
            if (self::$config_log_level > $log_level) {
                //log the message with log_level higher than the default value only only if log level isn't all messages.
                return;
            }
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