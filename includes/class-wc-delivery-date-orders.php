<?php
/**
 * Order Display & Management in Admin and Customer Accounts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Delivery_Date_Orders {

	public static function init() {
		// Customer view: Order Details (Thank You page & My Account > View Order)
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'display_delivery_info_customer' ), 15 );

		// Admin Order Edit screen (Display & Allow store admin to edit/update delivery schedule)
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( __CLASS__, 'display_admin_order_meta_fields' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_admin_order_meta_fields' ) );

		// Classic Orders Table columns
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_delivery_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_delivery_column_classic' ), 20, 2 );

		// HPOS (High-Performance Order Storage) Table columns
		add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'add_delivery_column' ), 20 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'render_delivery_column_hpos' ), 20, 2 );
	}

	/**
	 * Display delivery details on customer order pages (Thank You & My Account)
	 *
	 * @param WC_Order $order
	 */
	public static function display_delivery_info_customer( $order ) {
		if ( ! $order ) {
			return;
		}

		$details = WC_Delivery_Date_Emails::get_order_delivery_details( $order );
		if ( ! $details ) {
			return;
		}

		$formatted_date = $details['formatted_date'];
		$time = $details['time'];

		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );
		?>
		<section class="woocommerce-customer-details wc-delivery-date-order-details">
			<h2 class="woocommerce-column__title"><?php echo esc_html( $options['section_title'] ); ?></h2>
			<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
				<tbody>
					<?php if ( ! empty( $formatted_date ) ) : ?>
						<tr>
							<th scope="row"><strong><?php echo esc_html( $options['date_label'] ); ?>:</strong></th>
							<td><?php echo esc_html( $formatted_date ); ?></td>
						</tr>
					<?php endif; ?>

					<?php if ( ! empty( $time ) ) : ?>
						<tr>
							<th scope="row"><strong><?php echo esc_html( $options['time_label'] ); ?>:</strong></th>
							<td><?php echo esc_html( $time ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	/**
	 * Display & Edit Delivery info in Admin Order Details
	 *
	 * @param WC_Order $order
	 */
	public static function display_admin_order_meta_fields( $order ) {
		if ( ! $order ) {
			return;
		}

		$details = WC_Delivery_Date_Emails::get_order_delivery_details( $order );
		$date = $details ? $details['date'] : $order->get_meta( '_delivery_date' );
		$time = $details ? $details['time'] : $order->get_meta( '_delivery_time' );
		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );
		?>
		<div class="order_data_column" style="clear: both; margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
			<h3><?php echo esc_html( $options['section_title'] ); ?></h3>
			<p class="form-field form-field-wide">
				<label for="_delivery_date"><strong><?php echo esc_html( $options['date_label'] ); ?>:</strong></label>
				<input type="date" id="_delivery_date" name="_delivery_date" value="<?php echo esc_attr( $date ); ?>" style="width: 100%; max-width: 250px;" />
			</p>
			<p class="form-field form-field-wide">
				<label for="_delivery_time"><strong><?php echo esc_html( $options['time_label'] ); ?>:</strong></label>
				<input type="text" id="_delivery_time" name="_delivery_time" value="<?php echo esc_attr( $time ); ?>" placeholder="e.g. 09:00 AM - 12:00 PM" style="width: 100%; max-width: 250px;" />
			</p>
		</div>
		<?php
	}

	/**
	 * Save admin order meta changes
	 *
	 * @param int $order_id
	 */
	public static function save_admin_order_meta_fields( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( isset( $_POST['_delivery_date'] ) ) {
			$new_date = sanitize_text_field( wp_unslash( $_POST['_delivery_date'] ) );
			$order->update_meta_data( '_delivery_date', $new_date );
			if ( ! empty( $new_date ) ) {
				$formatted = date_i18n( get_option( 'date_format' ), strtotime( $new_date ) );
				$order->update_meta_data( '_delivery_date_formatted', $formatted );
			} else {
				$order->delete_meta_data( '_delivery_date_formatted' );
			}
		}

		if ( isset( $_POST['_delivery_time'] ) ) {
			$new_time = sanitize_text_field( wp_unslash( $_POST['_delivery_time'] ) );
			$order->update_meta_data( '_delivery_time', $new_time );
		}

		$order->save();
	}

	/**
	 * Register custom column header in orders table
	 */
	public static function add_delivery_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;
			// Place after order_status or order_date
			if ( 'order_status' === $key ) {
				$new_columns['wc_delivery_schedule'] = __( 'Delivery Schedule', 'wc-delivery-date' );
			}
		}

		// Fallback if key wasn't found
		if ( ! isset( $new_columns['wc_delivery_schedule'] ) ) {
			$new_columns['wc_delivery_schedule'] = __( 'Delivery Schedule', 'wc-delivery-date' );
		}

		return $new_columns;
	}

	/**
	 * Render column data in classic order list
	 */
	public static function render_delivery_column_classic( $column, $post_id ) {
		if ( 'wc_delivery_schedule' === $column ) {
			$order = wc_get_order( $post_id );
			if ( $order ) {
				self::render_schedule_badge( $order );
			}
		}
	}

	/**
	 * Render column data in HPOS order list
	 */
	public static function render_delivery_column_hpos( $column, $order ) {
		if ( 'wc_delivery_schedule' === $column && $order instanceof WC_Order ) {
			self::render_schedule_badge( $order );
		}
	}

	/**
	 * Helper: render the schedule badge HTML
	 */
	private static function render_schedule_badge( $order ) {
		$details = WC_Delivery_Date_Emails::get_order_delivery_details( $order );
		if ( ! $details ) {
			echo '<span style="color: #999;">&mdash;</span>';
			return;
		}

		$formatted_date = $details['formatted_date'];
		$time = $details['time'];

		echo '<div style="font-size: 13px; line-height: 1.4;">';
		if ( ! empty( $formatted_date ) ) {
			echo '<strong><span class="dashicons dashicons-calendar-alt" style="font-size: 15px; width: 15px; height: 15px; vertical-align: text-top; margin-right: 3px; color: #0073aa;"></span>' . esc_html( $formatted_date ) . '</strong>';
		}
		if ( ! empty( $time ) ) {
			echo '<br><span class="dashicons dashicons-clock" style="font-size: 15px; width: 15px; height: 15px; vertical-align: text-top; margin-right: 3px; color: #666;"></span><span style="color: #555;">' . esc_html( $time ) . '</span>';
		}
		echo '</div>';
	}
}
