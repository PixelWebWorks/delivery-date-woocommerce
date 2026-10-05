<?php
/**
 * Plugin Name: WooCommerce Delivery Date & Time Scheduler
 * Plugin URI:  https://github.com/PixelWebWorks/delivery-date-woocommerce
 * Description: Allows customers to select delivery date and time at checkout with admin capacity limits, lead times, allowed weekdays, and email/order notifications.
 * Version:     1.1.4
 * Author:      P!xel Web
 * Text Domain: wc-delivery-date
 * Domain Path: /languages
 * WC requires at least: 5.0
 * WC tested up to: 9.3
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'WC_DELIVERY_DATE_VERSION', '1.1.4' );
define( 'WC_DELIVERY_DATE_FILE', __FILE__ );
define( 'WC_DELIVERY_DATE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_DELIVERY_DATE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare HPOS (High-Performance Order Storage) Compatibility
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Main Plugin Initialization
 */
function wc_delivery_date_init() {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_delivery_date_missing_wc_notice' );
		return;
	}

	// Load includes
	require_once WC_DELIVERY_DATE_PATH . 'includes/class-wc-delivery-date-settings.php';
	require_once WC_DELIVERY_DATE_PATH . 'includes/class-wc-delivery-date-checkout.php';
	require_once WC_DELIVERY_DATE_PATH . 'includes/class-wc-delivery-date-orders.php';
	require_once WC_DELIVERY_DATE_PATH . 'includes/class-wc-delivery-date-emails.php';
	require_once WC_DELIVERY_DATE_PATH . 'includes/class-wc-delivery-date-ajax.php';

	// Initialize components
	WC_Delivery_Date_Settings::init();
	WC_Delivery_Date_Checkout::init();
	WC_Delivery_Date_Orders::init();
	WC_Delivery_Date_Emails::init();
	WC_Delivery_Date_AJAX::init();
}
add_action( 'plugins_loaded', 'wc_delivery_date_init' );

/**
 * Admin notice if WooCommerce is not installed or active
 */
function wc_delivery_date_missing_wc_notice() {
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_html_e( 'WooCommerce Delivery Date & Time Scheduler requires WooCommerce to be installed and active.', 'wc-delivery-date' ); ?></p>
	</div>
	<?php
}

/**
 * Helper function: Retrieve plugin options with default fallbacks
 */
function wc_delivery_date_get_setting( $key, $default = '' ) {
	$options = get_option( 'wc_delivery_date_settings', array() );
	return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

/**
 * Helper function: Count existing orders for a specific delivery date
 * Excludes cancelled, failed, refunded, and trashed orders.
 *
 * @param string $date Date in Y-m-d format
 * @return int Number of active orders for the given date
 */
function wc_delivery_date_get_order_count_for_date( $date ) {
	if ( empty( $date ) ) {
		return 0;
	}

	$orders = wc_get_orders( array(
		'limit'        => -1,
		'status'       => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' ),
		'meta_key'     => '_delivery_date',
		'meta_value'   => sanitize_text_field( $date ),
		'return'       => 'ids',
	) );

	return is_array( $orders ) ? count( $orders ) : 0;
}
