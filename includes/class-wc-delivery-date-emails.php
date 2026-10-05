<?php
/**
 * Delivery Date in WooCommerce Transactional Emails
 * (Customer & Admin notifications)
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

		// Standard WooCommerce email order meta fields filter
		add_filter( 'woocommerce_email_order_meta_fields', array( __CLASS__, 'add_delivery_to_email_meta_fields' ), 10, 3 );

		// Output in Plain text emails
		add_action( 'woocommerce_email_after_order_table_plain', array( __CLASS__, 'add_delivery_to_plain_emails' ), 15, 4 );
	}

	/**
	 * Helper to retrieve normalized delivery details from order
	 *
	 * @param WC_Order|int $order
	 * @return array|false
	 */
	public static function get_order_delivery_details( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		// Retrieve date from all possible meta keys (Blocks, Classic & HPOS)
		$date = $order->get_meta( '_delivery_date' );
		if ( empty( $date ) ) {
			$date = $order->get_meta( 'wc-delivery-date/delivery-date' );
		}
		if ( empty( $date ) ) {
			$date = $order->get_meta( '_wc-delivery-date/delivery-date' );
		}
		if ( empty( $date ) ) {
			$date = $order->get_meta( 'delivery_date' );
		}

		// Retrieve time from all possible meta keys
		$time = $order->get_meta( '_delivery_time' );
		if ( empty( $time ) ) {
			$time = $order->get_meta( 'wc-delivery-date/delivery-time' );
		}
		if ( empty( $time ) ) {
			$time = $order->get_meta( '_wc-delivery-date/delivery-time' );
		}
		if ( empty( $time ) ) {
			$time = $order->get_meta( 'delivery_time' );
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
	 * Add delivery fields to standard WooCommerce email meta list
	 *
	 * @param array    $fields
	 * @param bool     $sent_to_admin
	 * @param WC_Order $order
	 * @return array
	 */
	public static function add_delivery_to_email_meta_fields( $fields, $sent_to_admin, $order ) {
		$details = self::get_order_delivery_details( $order );
		if ( ! $details ) {
			return $fields;
		}

		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );

		if ( ! empty( $details['formatted_date'] ) ) {
			$fields['delivery_date'] = array(
				'label' => ! empty( $options['date_label'] ) ? $options['date_label'] : __( 'Delivery Date', 'wc-delivery-date' ),
				'value' => esc_html( $details['formatted_date'] ),
			);
		}

		if ( ! empty( $details['time'] ) ) {
			$fields['delivery_time'] = array(
				'label' => ! empty( $options['time_label'] ) ? $options['time_label'] : __( 'Delivery Time', 'wc-delivery-date' ),
				'value' => esc_html( $details['time'] ),
			);
		}

		return $fields;
	}

	/**
	 * Render delivery details in HTML emails
	 *
	 * @param WC_Order|int $order
	 * @param bool         $sent_to_admin
	 * @param bool         $plain_text
	 * @param WC_Email     $email
	 */
	public static function add_delivery_to_emails( $order, $sent_to_admin = false, $plain_text = false, $email = '' ) {
		if ( $plain_text ) {
			return;
		}

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order_id = $order->get_id();
		if ( isset( self::$rendered_orders[ $order_id ] ) ) {
			return; // Avoid duplicate render if multiple email hooks trigger
		}

		$details = self::get_order_delivery_details( $order );
		if ( ! $details ) {
			return;
		}

		self::$rendered_orders[ $order_id ] = true;
		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );
		?>
		<div style="margin-bottom: 25px; margin-top: 20px;">
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
	}

	/**
	 * Render delivery details in Plain Text emails
	 *
	 * @param WC_Order|int $order
	 * @param bool         $sent_to_admin
	 * @param bool         $plain_text
	 * @param WC_Email     $email
	 */
	public static function add_delivery_to_plain_emails( $order, $sent_to_admin = false, $plain_text = true, $email = '' ) {
		$details = self::get_order_delivery_details( $order );
		if ( ! $details ) {
			return;
		}

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
}
