<?php
/**
 * Delivery Date in WooCommerce Transactional Emails
 * (Customer & Admin notifications, YayMail & Custom Email Customizers support)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Delivery_Date_Emails {

	/**
	 * Track rendered orders to prevent duplicate outputs across hooks
	 *
	 * @var array
	 */
	private static $rendered_orders = array();

	public static function init() {
		// Output in HTML emails after order table
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'add_delivery_to_emails' ), 15, 4 );

		// Output in email order meta (fallback for custom email templates & themes)
		add_action( 'woocommerce_email_order_meta', array( __CLASS__, 'add_delivery_to_emails' ), 15, 4 );

		// Fallback for custom email designers (Kadence, YayMail Hook element, Email Customizer)
		add_action( 'woocommerce_email_customer_details', array( __CLASS__, 'add_delivery_to_emails' ), 5, 4 );

		// Output in Plain text emails
		add_action( 'woocommerce_email_after_order_table_plain', array( __CLASS__, 'add_delivery_to_plain_emails' ), 15, 4 );

		// YayMail Custom Shortcodes filter integration
		add_filter( 'yaymail_customs_shortcode', array( __CLASS__, 'register_yaymail_shortcodes' ), 10, 3 );

		// YayMail dynamic custom shortcode filters
		add_filter( 'yaymail_custom_shortcode_delivery_date', array( __CLASS__, 'yaymail_hook_delivery_date' ), 10, 2 );
		add_filter( 'yaymail_custom_shortcode_delivery_time', array( __CLASS__, 'yaymail_hook_delivery_time' ), 10, 2 );
		add_filter( 'yaymail_custom_shortcode_delivery_details', array( __CLASS__, 'yaymail_hook_delivery_details' ), 10, 2 );

		// Standard WordPress shortcodes (supported in text blocks across YayMail, Kadence, and standard page/email builders)
		add_shortcode( 'yaymail_custom_shortcode_delivery_date', array( __CLASS__, 'shortcode_delivery_date' ) );
		add_shortcode( 'yaymail_custom_shortcode_delivery_time', array( __CLASS__, 'shortcode_delivery_time' ) );
		add_shortcode( 'yaymail_custom_shortcode_delivery_details', array( __CLASS__, 'shortcode_delivery_details' ) );
		add_shortcode( 'wc_delivery_date', array( __CLASS__, 'shortcode_delivery_date' ) );
		add_shortcode( 'wc_delivery_time', array( __CLASS__, 'shortcode_delivery_time' ) );
		add_shortcode( 'wc_delivery_details', array( __CLASS__, 'shortcode_delivery_details' ) );
	}

	/**
	 * Helper to retrieve normalized delivery details from order
	 * Checks order meta, all known Blocks prefixes, metadata iteration, user meta, and active session
	 *
	 * @param WC_Order|int|null $order
	 * @return array|false
	 */
	public static function get_order_delivery_details( $order = null ) {
		if ( empty( $order ) ) {
			$order = self::get_current_order();
		}

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		// 1. Direct meta keys on the order
		$date_keys = array(
			'_delivery_date',
			'wc-delivery-date/delivery-date',
			'_wc-delivery-date/delivery-date',
			'_wc_contact/wc-delivery-date/delivery-date',
			'_wc_order/wc-delivery-date/delivery-date',
			'_wc_other/wc-delivery-date/delivery-date',
			'_wc_address/wc-delivery-date/delivery-date',
			'delivery_date',
			'_delivery_date_formatted',
		);

		$date = '';
		foreach ( $date_keys as $k ) {
			$val = $order->get_meta( $k );
			if ( ! empty( $val ) ) {
				$date = $val;
				break;
			}
		}

		// 2. Scan all order metadata objects for any delivery-date key
		if ( empty( $date ) && method_exists( $order, 'get_meta_data' ) ) {
			foreach ( $order->get_meta_data() as $meta_obj ) {
				$meta_data = is_object( $meta_obj ) && method_exists( $meta_obj, 'get_data' ) ? $meta_obj->get_data() : (array) $meta_obj;
				$key = isset( $meta_data['key'] ) ? $meta_data['key'] : '';
				if ( stripos( $key, 'delivery-date' ) !== false || stripos( $key, 'delivery_date' ) !== false ) {
					$val = isset( $meta_data['value'] ) ? $meta_data['value'] : '';
					if ( ! empty( $val ) ) {
						$date = $val;
						break;
					}
				}
			}
		}

		// 3. Fallback: check customer user meta if logged in
		if ( empty( $date ) && $order->get_customer_id() > 0 ) {
			foreach ( $date_keys as $k ) {
				$val = get_user_meta( $order->get_customer_id(), $k, true );
				if ( ! empty( $val ) ) {
					$date = $val;
					break;
				}
			}
		}

		// 4. Fallback: check active WooCommerce session
		if ( empty( $date ) && function_exists( 'WC' ) && WC()->session ) {
			$date = WC()->session->get( 'wc_delivery_date' );
		}

		// 5. Fallback: check WooCommerce customer object
		if ( empty( $date ) && function_exists( 'WC' ) && WC()->customer ) {
			$date = WC()->customer->get_meta( '_delivery_date' );
			if ( empty( $date ) ) {
				$date = WC()->customer->get_meta( 'wc-delivery-date/delivery-date' );
			}
		}

		// Time keys
		$time_keys = array(
			'_delivery_time',
			'wc-delivery-date/delivery-time',
			'_wc-delivery-date/delivery-time',
			'_wc_contact/wc-delivery-date/delivery-time',
			'_wc_order/wc-delivery-date/delivery-time',
			'_wc_other/wc-delivery-date/delivery-time',
			'delivery_time',
		);

		$time = '';
		foreach ( $time_keys as $k ) {
			$val = $order->get_meta( $k );
			if ( ! empty( $val ) ) {
				$time = $val;
				break;
			}
		}

		if ( empty( $time ) && function_exists( 'WC' ) && WC()->session ) {
			$time = WC()->session->get( 'wc_delivery_time' );
		}

		if ( empty( $time ) && function_exists( 'WC' ) && WC()->customer ) {
			$time = WC()->customer->get_meta( '_delivery_time' );
		}

		if ( empty( $date ) && empty( $time ) ) {
			return false;
		}

		// Format date using WordPress store date format setting
		$formatted_date = $order->get_meta( '_delivery_date_formatted' );
		if ( empty( $formatted_date ) && ! empty( $date ) ) {
			$ts = strtotime( $date );
			if ( $ts ) {
				$formatted_date = date_i18n( get_option( 'date_format' ), $ts );
			} else {
				$formatted_date = $date;
			}
		}

		return array(
			'date'           => $date,
			'formatted_date' => $formatted_date,
			'time'           => $time,
		);
	}

	/**
	 * Helper to safely resolve current WC_Order context across hooks and builders
	 *
	 * @param int|null $order_id
	 * @return WC_Order|null
	 */
	public static function get_current_order( $order_id = 0 ) {
		if ( ! empty( $order_id ) ) {
			$order = wc_get_order( absint( $order_id ) );
			if ( $order instanceof WC_Order ) {
				return $order;
			}
		}

		// 1. Check YayMail order reference
		if ( isset( $GLOBALS['yaymail_order'] ) && $GLOBALS['yaymail_order'] instanceof WC_Order ) {
			return $GLOBALS['yaymail_order'];
		}

		// 2. Check global order in WooCommerce email rendering
		if ( isset( $GLOBALS['order'] ) && $GLOBALS['order'] instanceof WC_Order ) {
			return $GLOBALS['order'];
		}

		// 3. Check admin order screens
		if ( isset( $GLOBALS['theorder'] ) && $GLOBALS['theorder'] instanceof WC_Order ) {
			return $GLOBALS['theorder'];
		}

		// 4. Check query vars (order-received)
		if ( function_exists( 'get_query_var' ) ) {
			$order_received_id = get_query_var( 'order-received' );
			if ( ! empty( $order_received_id ) ) {
				$order = wc_get_order( absint( $order_received_id ) );
				if ( $order instanceof WC_Order ) {
					return $order;
				}
			}
		}

		// 5. Check URL parameters
		if ( isset( $_GET['order_id'] ) ) {
			$order = wc_get_order( absint( $_GET['order_id'] ) );
			if ( $order instanceof WC_Order ) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * Generate delivery details HTML block for emails & shortcodes
	 *
	 * @param WC_Order|int|null $order
	 * @return string
	 */
	public static function get_delivery_html( $order = null ) {
		if ( empty( $order ) ) {
			$order = self::get_current_order();
		}

		$details = self::get_order_delivery_details( $order );
		if ( ! $details ) {
			return '';
		}

		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );

		ob_start();
		?>
		<div class="wc-delivery-date-email-details" style="margin-bottom: 25px; margin-top: 20px;">
			<h2 style="color: #444; font-size: 16px; font-weight: bold; margin: 0 0 10px; border-bottom: 2px solid #eee; padding-bottom: 6px;">
				<?php echo esc_html( $options['section_title'] ); ?>
			</h2>
			<table cellspacing="0" cellpadding="8" style="width: 100%; border: 1px solid #e5e5e5; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border-collapse: collapse;" border="1">
				<tbody>
					<?php if ( ! empty( $details['formatted_date'] ) ) : ?>
						<tr>
							<th scope="row" style="text-align: left; border: 1px solid #eee; padding: 10px 12px; background-color: #f8fafc; width: 35%; color: #475569; font-size: 14px;">
								<strong><?php echo esc_html( $options['date_label'] ); ?>:</strong>
							</th>
							<td style="text-align: left; border: 1px solid #eee; padding: 10px 12px; color: #0f172a; font-size: 14px; font-weight: 500;">
								<?php echo esc_html( $details['formatted_date'] ); ?>
							</td>
						</tr>
					<?php endif; ?>

					<?php if ( ! empty( $details['time'] ) ) : ?>
						<tr>
							<th scope="row" style="text-align: left; border: 1px solid #eee; padding: 10px 12px; background-color: #f8fafc; width: 35%; color: #475569; font-size: 14px;">
								<strong><?php echo esc_html( $options['time_label'] ); ?>:</strong>
							</th>
							<td style="text-align: left; border: 1px solid #eee; padding: 10px 12px; color: #0f172a; font-size: 14px; font-weight: 500;">
								<?php echo esc_html( $details['time'] ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Sample delivery HTML block used in email builder preview editors (YayMail, Kadence, etc.)
	 *
	 * @return string
	 */
	public static function get_delivery_html_preview() {
		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );
		$sample_date = date_i18n( get_option( 'date_format' ), strtotime( '+2 days' ) );

		ob_start();
		?>
		<div class="wc-delivery-date-email-details" style="margin-bottom: 25px; margin-top: 20px;">
			<h2 style="color: #444; font-size: 16px; font-weight: bold; margin: 0 0 10px; border-bottom: 2px solid #eee; padding-bottom: 6px;">
				<?php echo esc_html( $options['section_title'] ); ?>
			</h2>
			<table cellspacing="0" cellpadding="8" style="width: 100%; border: 1px solid #e5e5e5; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border-collapse: collapse;" border="1">
				<tbody>
					<tr>
						<th scope="row" style="text-align: left; border: 1px solid #eee; padding: 10px 12px; background-color: #f8fafc; width: 35%; color: #475569; font-size: 14px;">
							<strong><?php echo esc_html( $options['date_label'] ); ?>:</strong>
						</th>
						<td style="text-align: left; border: 1px solid #eee; padding: 10px 12px; color: #0f172a; font-size: 14px; font-weight: 500;">
							<?php echo esc_html( $sample_date ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render delivery details in HTML emails
	 *
	 * @param WC_Order|int $order
	 * @param bool         $sent_to_admin
	 * @param bool         $plain_text
	 * @param WC_Email|string $email
	 */
	public static function add_delivery_to_emails( $order = null, $sent_to_admin = false, $plain_text = false, $email = '' ) {
		if ( $plain_text || empty( $order ) ) {
			return;
		}

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order_id  = $order->get_id();
		$email_id  = is_object( $email ) && isset( $email->id ) ? $email->id : ( $sent_to_admin ? 'admin' : 'customer' );
		$dedup_key = $email_id . '_' . $order_id;
		if ( isset( self::$rendered_orders[ $dedup_key ] ) ) {
			return; // Avoid duplicate render within the same email
		}

		$html = self::get_delivery_html( $order );
		if ( ! empty( $html ) ) {
			self::$rendered_orders[ $dedup_key ] = true;
			echo $html;
		}
	}

	/**
	 * Render delivery details in Plain Text emails
	 *
	 * @param WC_Order|int $order
	 * @param bool         $sent_to_admin
	 * @param bool         $plain_text
	 * @param WC_Email     $email
	 */
	public static function add_delivery_to_plain_emails( $order = null, $sent_to_admin = false, $plain_text = true, $email = '' ) {
		if ( empty( $order ) ) {
			return;
		}

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order_id  = $order->get_id();
		$email_id  = is_object( $email ) && isset( $email->id ) ? $email->id : ( $sent_to_admin ? 'admin' : 'customer' );
		$dedup_key = 'plain_' . $email_id . '_' . $order_id;
		if ( isset( self::$rendered_orders[ $dedup_key ] ) ) {
			return;
		}

		$details = self::get_order_delivery_details( $order );
		if ( ! $details ) {
			return;
		}

		self::$rendered_orders[ $dedup_key ] = true;

		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );

		echo "\n" . strtoupper( $options['section_title'] ) . "\n";
		echo str_repeat( '=', 30 ) . "\n";
		if ( ! empty( $details['formatted_date'] ) ) {
			echo $options['date_label'] . ': ' . $details['formatted_date'] . "\n";
		}
		if ( ! empty( $details['time'] ) ) {
			echo $options['time_label'] . ': ' . $details['time'] . "\n";
		}
		echo "\n";
	}

	/**
	 * YayMail Custom Shortcode Filter Callback
	 *
	 * @param array       $shortcode_list
	 * @param mixed       $yaymail_informations
	 * @param array       $args
	 * @return array
	 */
	public static function register_yaymail_shortcodes( $shortcode_list, $yaymail_informations, $args = array() ) {
		if ( ! is_array( $shortcode_list ) ) {
			$shortcode_list = array();
		}

		$order = null;
		if ( is_array( $args ) && ! empty( $args['order'] ) && $args['order'] instanceof WC_Order ) {
			$order = $args['order'];
		} elseif ( is_array( $args ) && ! empty( $args['order_id'] ) ) {
			$order = wc_get_order( absint( $args['order_id'] ) );
		} elseif ( is_object( $yaymail_informations ) && ! empty( $yaymail_informations->order ) && $yaymail_informations->order instanceof WC_Order ) {
			$order = $yaymail_informations->order;
		} else {
			$order = self::get_current_order();
		}

		if ( $order instanceof WC_Order ) {
			$details = self::get_order_delivery_details( $order );
			$date = ( $details && ! empty( $details['formatted_date'] ) ) ? $details['formatted_date'] : '';
			$time = ( $details && ! empty( $details['time'] ) ) ? $details['time'] : '';
			$html = $details ? self::get_delivery_html( $order ) : '';
		} else {
			// Editor preview fallback when no order selected
			$date = date_i18n( get_option( 'date_format' ), strtotime( '+2 days' ) );
			$time = '10:00 AM - 02:00 PM';
			$html = self::get_delivery_html_preview();
		}

		// Register with brackets and without brackets for universal YayMail parser compatibility
		$shortcode_list['[yaymail_custom_shortcode_delivery_date]']    = $date;
		$shortcode_list['yaymail_custom_shortcode_delivery_date']      = $date;
		$shortcode_list['[yaymail_custom_shortcode_delivery_time]']    = $time;
		$shortcode_list['yaymail_custom_shortcode_delivery_time']      = $time;
		$shortcode_list['[yaymail_custom_shortcode_delivery_details]'] = $html;
		$shortcode_list['yaymail_custom_shortcode_delivery_details']   = $html;

		$shortcode_list['[wc_delivery_date]']    = $date;
		$shortcode_list['wc_delivery_date']      = $date;
		$shortcode_list['[wc_delivery_time]']    = $time;
		$shortcode_list['wc_delivery_time']      = $time;
		$shortcode_list['[wc_delivery_details]'] = $html;
		$shortcode_list['wc_delivery_details']   = $html;

		return $shortcode_list;
	}

	/**
	 * YayMail dynamic filter: yaymail_custom_shortcode_delivery_date
	 */
	public static function yaymail_hook_delivery_date( $content = '', $order_id = 0 ) {
		$order = self::get_current_order( $order_id );
		if ( $order ) {
			$details = self::get_order_delivery_details( $order );
			return ( $details && ! empty( $details['formatted_date'] ) ) ? $details['formatted_date'] : '';
		}
		return date_i18n( get_option( 'date_format' ), strtotime( '+2 days' ) );
	}

	/**
	 * YayMail dynamic filter: yaymail_custom_shortcode_delivery_time
	 */
	public static function yaymail_hook_delivery_time( $content = '', $order_id = 0 ) {
		$order = self::get_current_order( $order_id );
		if ( $order ) {
			$details = self::get_order_delivery_details( $order );
			return ( $details && ! empty( $details['time'] ) ) ? $details['time'] : '';
		}
		return '10:00 AM - 02:00 PM';
	}

	/**
	 * YayMail dynamic filter: yaymail_custom_shortcode_delivery_details
	 */
	public static function yaymail_hook_delivery_details( $content = '', $order_id = 0 ) {
		$order = self::get_current_order( $order_id );
		if ( $order ) {
			return self::get_delivery_html( $order );
		}
		return self::get_delivery_html_preview();
	}

	/**
	 * Standard WordPress Shortcode: [wc_delivery_date]
	 */
	public static function shortcode_delivery_date( $atts = array() ) {
		$atts = shortcode_atts( array(
			'order_id' => 0,
		), $atts, 'wc_delivery_date' );

		$order = self::get_current_order( $atts['order_id'] );
		if ( $order ) {
			$details = self::get_order_delivery_details( $order );
			return ( $details && ! empty( $details['formatted_date'] ) ) ? esc_html( $details['formatted_date'] ) : '';
		}
		return '';
	}

	/**
	 * Standard WordPress Shortcode: [wc_delivery_time]
	 */
	public static function shortcode_delivery_time( $atts = array() ) {
		$atts = shortcode_atts( array(
			'order_id' => 0,
		), $atts, 'wc_delivery_time' );

		$order = self::get_current_order( $atts['order_id'] );
		if ( $order ) {
			$details = self::get_order_delivery_details( $order );
			return ( $details && ! empty( $details['time'] ) ) ? esc_html( $details['time'] ) : '';
		}
		return '';
	}

	/**
	 * Standard WordPress Shortcode: [wc_delivery_details]
	 */
	public static function shortcode_delivery_details( $atts = array() ) {
		$atts = shortcode_atts( array(
			'order_id' => 0,
		), $atts, 'wc_delivery_details' );

		$order = self::get_current_order( $atts['order_id'] );
		if ( $order ) {
			return self::get_delivery_html( $order );
		}
		return '';
	}
}
