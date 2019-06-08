<?php

class WC_Buyte_Settings extends WC_Settings_Page {

    public $WC_Buyte;

    /**
	 * Constructor.
	 */
	public function __construct(WC_Buyte $WC_Buyte) {
        $this->WC_Buyte = $WC_Buyte;
		$this->id    = $this->WC_Buyte->WC_Buyte_Config->id;
		$this->label = __( $this->WC_Buyte->WC_Buyte_Config->label, 'woocommerce' );

		parent::__construct();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = array(
			'' => __( 'Buyte settings', 'woocommerce' ),
		);
		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Get settings array.
	 *
	 * @param string $current_section Section being shown.
	 * @return array
	 */
	public function get_settings(){
		$settings = $this->WC_Buyte->WC_Buyte_Config->get_settings();
        $settings = apply_filters( 'woocommerce_buyte_settings', $settings );
		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
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
		WC_Admin_Settings::save_fields( $settings );
	}
}