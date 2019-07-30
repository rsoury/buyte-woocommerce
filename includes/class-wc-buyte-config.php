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
    const CONFIG_DEVELOPER_MODE = 'developer_mode';
    const CONFIG_TOGGLE_DEVELOPER_SETTINGS = 'toggle_dev_settings';

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

    public static $_id = 'buyte';
    public static $_label = 'Buyte';
    public static $_description = 'Offer your customers Apple Pay and Google Pay through a widget that sits on your website. By integrating Buyte into your e-commerce website, your visitors can securely checkout with their mobile wallet.';
    public static $_website = 'https://www.buytecheckout.com/?utm_source=woocommerce&utm_medium=plugin&utm_campaign=buyte';
    public static $_dashboard = 'https://dashboard.buytecheckout.com/?utm_source=woocommerce&utm_medium=plugin&utm_campaign=buyte';

    public static $config_log_level = self::LOG_LEVEL_ALL;
    public static $logger;

    public function __construct(WC_Buyte $WC_Buyte){
        $this->WC_Buyte = $WC_Buyte;
        $this->id = self::$_id;
        $this->label = self::$_label;
        $this->settings_description = self::$_description;
        $this->settings_website = self::$_website;
        $this->settings_dashboard = self::$_dashboard;
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
        $admin_js_url = plugin_dir_url( __FILE__ ) . '../assets/css/admin.js';
        $admin_css_url = plugin_dir_url( __FILE__ ) . '../assets/css/admin.css';
        wp_register_style( 'buyte-admin-style', $admin_css_url );
        wp_enqueue_style( 'buyte-admin-style' );
        wp_register_script( 'buyte-admin-script', $admin_js_url );
        wp_localize_script( 'buyte-admin-script', 'config', array(
            'toggle' => self::CONFIG_TOGGLE_DEVELOPER_SETTINGS,
            'affected' => array(
                self::CONFIG_LOGGING_LEVEL,
                self::CONFIG_DEVELOPER_MODE
            )
        ) );
        wp_enqueue_script( 'buyte-admin-script' );
    }

	public function get_settings(){
		$settings = array(
            array(
                'id'    => $this->id . '_title',
                'title' => __( $this->label . ' settings', 'woocommerce' ),
                'type'  => 'title',
                'desc' => sprintf(__('<a href="%s" target="_blank" rel="noopener noreferrer">Don\'t have your Buyte account and credentials?</a>', 'woocommerce'), $this->settings_website)
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
                'desc' => sprintf(__('Can be obtained by created a Buyte Checkout in the <a href="%s" target="_blank" rel="noopener noreferrer">Buyte Dashboard</a>', 'woocommerce'), $this->settings_dashboard),
                'default' => ''
			),
			array(
                'id' => self::CONFIG_PUBLIC_KEY,
				'title' => __('Public Key', 'woocommerce'),
                'type' => 'text',
                'desc' => sprintf(__('Get your Public Key in the <a href="%s" target="_blank" rel="noopener noreferrer">Buyte Dashboard</a>', 'woocommerce'), $this->settings_dashboard),
                'default' => ''
			),
			array(
                'id' => self::CONFIG_SECRET_KEY,
				'title' => __('Secret Key', 'woocommerce'),
                'type' => 'password',
                'desc' => sprintf(__('Get your Public Key in the <a href="%s" target="_blank" rel="noopener noreferrer">Buyte Dashboard</a>', 'woocommerce'), $this->settings_dashboard),
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
                'id' => self::CONFIG_TOGGLE_DEVELOPER_SETTINGS,
                'title' => __('Show Developer Settings', 'woocommerce'),
                'type' => 'checkbox',
                'default' => 'no'
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
                ),
            ),
            array(
                'id' => self::CONFIG_DEVELOPER_MODE,
				'title' => __('Developer Mode', 'woocommerce'),
                'type' => 'checkbox',
                'desc' => __('<p>Leave OFF unless in active development or in demonstration. Toggles the use of Buyte developer resources and API. Requires Development credentials.</p><p>To test checkouts, please use Test Checkout Connection created through the Buyte Merchant Dashboard.</p>', 'woocommerce'),
                'default' => 'no',
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

    public static function is_developer_mode() {
        return WC_Admin_Settings::get_option(self::CONFIG_TOGGLE_DEVELOPER_SETTINGS, 'yes') && WC_Admin_Settings::get_option(self::CONFIG_DEVELOPER_MODE, 'yes');
    }

    public static function get_id(){
        return self::$_id;
    }
    public static function get_label(){
        return self::$_label;
    }
    public static function get_description(){
        return self::$_description;
    }
    public static function get_website_url(){
        return self::$_website;
    }
    public static function get_dashboard_url(){
        return self::$_dashboard;
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