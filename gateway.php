<?php

/**
 * Plugin Name: MiFinity Payments Gateway
 * Plugin URI: https://wordpress.org/plugins/mifinity-payments-gateway
 * Author: MiFinity Payments
 * Author URI: https://www.mifinity.com
 * Description: Securely accept credit and debit cards on your WooCommerce site via MiFinity Payments.
 * Version: 1.0.9
 * Requires at least: 4.4
 * Tested up to: 5.8
 * Requires PHP: 5.6
 * text-domain: mifinity-pay-woo
 * Copyright: © MiFinity Payments
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Class MiFinity_Payment_Gateway file.
 *
 * @package WooCommerce\MiFinity
 * 
 * 
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters(
    'active_plugins', get_option('active_plugins')
))) return;

/**
 * Main MiFinity class which sets the gateway up
 */
class WC_MiFinity {

    /**
     * @var Singleton The reference the *Singleton* instance of this class
     */
    private static $instance;

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Notices (array)
     * @var array
     */
    public $notices = array();

    /**
     * Constructor
     */
    public function __construct() {
        // Actions
        add_action( 'admin_init', array( $this, 'check_environment' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    public function settings_url() {
        return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mifinity_payment' );
    }

    /**
     * Allow this class and other classes to add slug keyed notices (to avoid duplication)
     */
    public function add_admin_notice( $slug, $class, $message ) {
        $this->notices[$slug] = array(
            'class'   => $class,
            'message' => $message,
        );
    }

    public function check_environment() {

        // Check if api key is present. Otherwise prompt, via notice, to go to setting.
        $options = get_option( 'woocommerce_mifinity_payment_settings' );

        $secret = isset( $options['api_key'] ) ? $options['account_number'] : '';

        if ( empty( $secret ) && !( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'mifinity_payment' === $_GET['section'] ) ) {
            $setting_link = $this->settings_url();
            $this->add_admin_notice( 'prompt_connect', 'notice notice-warning', sprintf( __( 'MiFinity is almost ready. To get started, <a href="%s">set your MiFinity account keys</a>.', 'mifinity_payment' ), $setting_link ) );
        }

    }

    /**
     * Display any notices we've collected thus far (e.g. for connection, disconnection)
     */
    public function admin_notices() {
        foreach ( (array) $this->notices as $notice_key => $notice ) {
            echo  "<div class='" . esc_attr( $notice['class'] ) . "'><p>" ;
            echo  wp_kses( $notice['message'], array(
                'a' => array(
                'href' => array(),
            ),
            ) ) ;
            echo  '</p></div>' ;
        }
    }

    /**
     * Add relevant links to plugins page
     * @param  array $links
     * @return array
     */
    public function plugin_action_links( $links ) {
        $plugin_links = array( '<a href="' . $this->settings_url() . '">' . __( 'Settings', 'mifinity_payment' ));
        return array_merge( $plugin_links, $links );
    }

    /**
     * Init localisations and files
     */
    public function init() {

        // Init the gateway itself
        $this->init_gateways();

        // required files
        require_once dirname( __FILE__ ) . '/includes/class-wc-payment-gateway-mifinity-logger.php';

        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ), 11 );
    }

    /**
     * Initialize the gateway
     */
    public function init_gateways() {
        if ( !class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }
        require_once dirname( __FILE__ ) . '/includes/class-wc-payment-gateway-mifinity.php';
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
    }

    /**
     * Add the gateways to WooCommerce
     */
    public function add_gateways( $methods ) {
        $methods[] = 'MiFinity_Payment_Gateway';
        return $methods;
    }

}
$GLOBALS['mifinity-pay-woo'] = WC_MiFinity::get_instance();

?>