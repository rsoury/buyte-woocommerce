<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides static methods as helpers.
 */
class WC_Buyte_Util {
    public static function get_amount($total) {
		return absint( wc_format_decimal( ( (float) $total * 100 ), wc_get_price_decimals() ) ); // In cents.
	}

    public static function get_price(int $buyte_amount, bool $force_decimal = true, bool $html = false) {
		$price = wc_price( $force_decimal ? $buyte_amount / 100 : $buyte_amount );

		if( $html ){
			return $price;
		}

		return (float) str_replace( "&#36;", "", strip_tags( $price ) );
	}

	public static function get_cart_discount() {
		// Add discounts from cart
		if ( WC()->cart->has_discount() ) {
			$discounts = 0;
			if ( self::is_wc_lt( '3.2' ) ) {
				$discounts = wc_format_decimal( WC()->cart->get_cart_discount_total(), WC()->cart->dp );
			} else {
				$applied_coupons = array_values( WC()->cart->get_coupon_discount_totals() );
				foreach ( $applied_coupons as $amount ) {
					$discounts += (float) $amount;
				}
			}
			$discounts = wc_format_decimal( $discounts, WC()->cart->dp );
			$amount = self::get_amount( $discounts );
			if(!empty( $amount )){
				return $amount;
			}
		}
		return 0;
	}

	public static function get_cart_tax() {
		if( wc_tax_enabled() ){
			$tax = wc_format_decimal( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp );
			$amount = self::get_amount( $tax );
			if(!empty( $amount )){
				return $amount;
			}
		}
		return 0;
	}

	// See: https://stackoverflow.com/questions/13637145/split-text-string-into-first-and-last-name-in-php
	public static function split_name($name) {
		$name = trim($name);
		$last_name = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
		$first_name = trim( preg_replace('#'.$last_name.'#', '', $name ) );
		return array($first_name, $last_name);
	}

	/**
	 * Checks if WC version is less than passed in version.
	 *
	 * @param string $version Version to check against.
	 * @return bool
	 */
	public static function is_wc_lt( $version ) {
        return version_compare(WC_VERSION, $version, '<');
	}

	public static function debug_log($log){
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) );
		} else {
			error_log( $log );
		}
	}

    public static function get_wc_version() {
		return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
	}

	public static function is_wc_version_gte_3_0() {
		return self::get_wc_version() && version_compare( self::get_wc_version(), '3.0', '>=' );
	}
}
