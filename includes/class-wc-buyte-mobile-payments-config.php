<?php

class WC_Buyte_Mobile_Payments_Config extends WC_Settings_API{

	// Admin Setting Keys
	const CONFIG_ENABLED = 'enabled';
	const CONFIG_PUBLIC_KEY = 'public_key';
	const CONFIG_LOGGING_LEVEL = 'log_level';
	const CONFIG_DISPLAY_CHECKOUT = 'display_checkout';
	const CONFIG_DISPLAY_CART = 'display_cart';
	const CONFIG_DISPLAY_PRODUCT = 'display_product';
    const CONFIG_SHIPPING_METHODS = 'shipping_methods';

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


	public $WC_Buyte_Mobile_Payments;

    public $id = 'buyte';
    public $form_fields;
    public $settings_title = 'Buyte Settings';
    public $settings_description = 'Buyte provides a widget that allows you to implement Apple Pay and have it exposed across all browsers and devices.';
    public $settings_webite = 'https://buyte.co/';

    public static $config_level = self::LOG_LEVEL_ALL;
    public static $logger;

	public function __construct(WC_Buyte_Mobile_Payments $WC_Buyte_Mobile_Payments){
		$this->WC_Buyte_Mobile_Payments = $WC_Buyte_Mobile_Payments;
        $this->form_fields = $this->get_settings();
	}

	public function init(){
		add_filter( 'woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50 );
        add_action( 'woocommerce_settings_tabs_settings_tab_' . $this->id, array($this, 'settings_tab') );
        //save admin options
        add_action( 'woocommerce_update_options_settings_tab_' . $this->id, array($this, 'process_admin_options') );
        // Plugin settings link
        add_filter( 'plugin_action_links_' . $this->WC_Buyte_Mobile_Payments->basename(), array($this, 'plugin_settings_link') );
	}

	public function settings_tab(){
        $admin_option_js = esc_url(plugins_url('assets/js/admin_options.js', dirname(__FILE__)));
        $admin_options_css = esc_url(plugins_url('assets/css/admin.css', dirname(__FILE__)));

        include plugin_dir_path( __FILE__ ) . '/view/admin/settings.php';
	}
    public function process_admin_options(){
        $result = parent::process_admin_options();

        self::$config_level = $this->get_option(self::CONFIG_LOGGING_LEVEL);

        return $result;
    }
	public function get_settings(){
		$settings = array(
			self::CONFIG_ENABLED => array(
				'title' => __('Enable/Disable', 'woocommerce'),
                'label' => __('Enable Buyte Mobile Payments', 'woocommerce'),
                'type' => 'checkbox',
                'desc' => '',
                'default' => 'no'
			),
			self::CONFIG_PUBLIC_KEY => array(
				'title' => __('Public Key', 'woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('Get your Public Key from <a href="%s" target="_blank">Buyte</a>', 'woocommerce'), $settings_webite),
                'default' => ''
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
            self::CONFIG_SHIPPING_METHODS => array(
                'title' => __('Enter your shipping methods', 'woocommerce'),
                'desc_tip' => __('These are shipping methods that will appear in your Apple Pay payment sheet', 'woocommerce')
            )
		);

		return $settings;
	}

	public function add_settings_tab( $settings_tabs ) {
        $settings_tabs['settings_tab_buyte'] = 'Buyte';
        return $settings_tabs;
    }

    public function get_public_key(){
        return $this->get_option(self::CONFIG_PUBLIC_KEY);
    }

    public function is_enabled(){
        return $this->get_option(self::CONFIG_ENABLED) === 'yes';
    }

    public function get_shipping_methods(){
        $shipping_methods = json_decode($this->get_option(self::CONFIG_SHIPPING_METHODS));
        if($shipping_methods){
            return $shipping_methods;
        }
        return array();
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
        self::$logger->add($this->id, $message);
    }
}