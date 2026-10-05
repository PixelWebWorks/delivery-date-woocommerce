<?php
/**
 * Delivery Date in WooCommerce Transactional Emails
 * (Customer & Admin notifications)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Delivery_Date_Emails {

	public static function init() {
		// Output in HTML emails after order table
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'add_delivery_to_emails' ), 15, 4 );

		// Output in Plain text emails
		add_action( 'woocommerce_email_after_order_table_plain', array( __CLASS__, 'add_delivery_to_plain_emails' ), 15, 4 );
	}

	/**
	 * Render delivery details in HTML emails
	 *
	 * @param WC_Order $order
	 * @param bool     $sent_to_admin
	 * @param bool     $plain_text
	 * @param WC_Email $email
	 */
	public static function add_delivery_to_emails( $order, $sent_to_admin, $plain_text = false, $email = '' ) {
		if ( ! $order ) {
			return;
		}

		$date = $order->get_meta( '_delivery_date' );
		$time = $order->get_meta( '_delivery_time' );

		if ( empty( $date ) && empty( $time ) ) {
			return;
		}

		$formatted_date = $order->get_meta( '_delivery_date_formatted' );
		if ( empty( $formatted_date ) && ! empty( $date ) ) {
			$formatted_date = date_i18n( get_option( 'date_format' ), strtotime( $date ) );
		}

		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );
		?>
		<div style="margin-bottom: 30px; margin-top: 20px;">
			<h2 style="color: #444; font-size: 16px; font-weight: bold; margin: 0 0 10px; border-bottom: 2px solid #eee; padding-bottom: 5px;">
				<?php echo esc_html( $options['section_title'] ); ?>
			</h2>
			<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #e5e5e5; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
				<tbody>
					<?php if ( ! empty( $formatted_date ) ) : ?>
						<tr>
							<th scope="row" style="text-align: left; border: 1px solid #eee; padding: 10px; background-color: #fafafa; width: 35%; color: #555;">
								<strong><?php echo esc_html( $options['date_label'] ); ?>:</strong>
							</th>
							<td style="text-align: left; border: 1px solid #eee; padding: 10px; color: #111;">
								<?php echo esc_html( $formatted_date ); ?>
							</td>
						</tr>
					<?php endif; ?>

					<?php if ( ! empty( $time ) ) : ?>
						<tr>
							<th scope="row" style="text-align: left; border: 1px solid #eee; padding: 10px; background-color: #fafafa; width: 35%; color: #555;">
								<strong><?php echo esc_html( $options['time_label'] ); ?>:</strong>
							</th>
							<td style="text-align: left; border: 1px solid #eee; padding: 10px; color: #111;">
								<?php echo esc_html( $time ); ?>
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
	 * @param WC_Order $order
	 * @param bool     $sent_to_admin
	 * @param bool     $plain_text
	 * @param WC_Email $email
	 */
	public static function add_delivery_to_plain_emails( $order, $sent_to_admin, $plain_text = true, $email = '' ) {
		if ( ! $order ) {
			return;
		}

		$date = $order->get_meta( '_delivery_date' );
		$time = $order->get_meta( '_delivery_time' );

		if ( empty( $date ) && empty( $time ) ) {
			return;
		}

		$formatted_date = $order->get_meta( '_delivery_date_formatted' );
		if ( empty( $formatted_date ) && ! empty( $date ) ) {
			$formatted_date = date_i18n( get_option( 'date_format' ), strtotime( $date ) );
		}

		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );

		echo "\n" . strtoupper( $options['section_title'] ) . "\n";
		echo str_repeat( '=', 30 ) . "\n";
		if ( ! empty( $formatted_date ) ) {
			echo $options['date_label'] . ': ' . $formatted_date . "\n";
		}
		if ( ! empty( $time ) ) {
			echo $options['time_label'] . ': ' . $time . "\n";
		}
		echo "\n";
	}
}
