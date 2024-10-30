<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * logging class which saves data to the log
 */
class WC_MiFinity_Logger {

	public static $logger;

	public static function log( $message ) {

		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
 		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->debug( $message, array( 'source' => 'woocommerce-gateway-mifinity_payment' ) );

	}
}

new WC_MiFinity_Logger();