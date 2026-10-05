<?php
/**
 * Checkout Fields, Enqueue, Validation & Order Saving
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Delivery_Date_Checkout {

	private static $is_rendered = false;

	public static function init() {
		// Classic checkout hooks (Render at the very top of checkout form)
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_checkout_fields' ), 5 );
		add_action( 'woocommerce_checkout_before_customer_details', array( __CLASS__, 'render_checkout_fields' ), 5 );
		add_action( 'woocommerce_review_order_before_payment', array( __CLASS__, 'render_checkout_fields' ), 5 );
		add_action( 'woocommerce_after_order_notes', array( __CLASS__, 'render_checkout_fields' ), 20 );

		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'validate_checkout_fields' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_order_delivery_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'save_order_delivery_meta_classic' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'ensure_order_delivery_meta_on_process' ), 10, 3 );

		// WooCommerce Checkout Blocks API hooks (WooCommerce 8.6+)
		add_action( 'woocommerce_init', array( __CLASS__, 'register_blocks_checkout_fields' ) );
		add_action( 'woocommerce_set_additional_field_value', array( __CLASS__, 'save_blocks_additional_field' ), 10, 4 );
		add_action( 'woocommerce_validate_additional_field', array( __CLASS__, 'validate_blocks_additional_field' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'save_store_api_order_meta' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'save_store_api_order_meta' ), 10, 1 );

		// Enqueue scripts & styles (Classic & Blocks)
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_checkout_assets' ) );
	}

	/**
	 * Get merged options
	 */
	public static function get_options() {
		return wp_parse_args(
			get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ),
			WC_Delivery_Date_Settings::get_defaults()
		);
	}

	/**
	 * Enqueue checkout assets
	 */
	public static function enqueue_checkout_assets() {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		$options = self::get_options();
		if ( empty( $options['enabled'] ) ) {
			return;
		}

		// Enqueue Flatpickr styles & script from CDN
		wp_enqueue_style(
			'wc-delivery-date-flatpickr-css',
			'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css',
			array(),
			'4.6.13'
		);

		wp_enqueue_style(
			'wc-delivery-date-checkout-css',
			WC_DELIVERY_DATE_URL . 'assets/css/checkout.css',
			array( 'wc-delivery-date-flatpickr-css' ),
			WC_DELIVERY_DATE_VERSION
		);

		wp_enqueue_script(
			'wc-delivery-date-flatpickr-js',
			'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
			array( 'jquery' ),
			'4.6.13',
			true
		);

		wp_enqueue_script(
			'wc-delivery-date-checkout-js',
			WC_DELIVERY_DATE_URL . 'assets/js/checkout.js',
			array( 'jquery', 'wc-delivery-date-flatpickr-js' ),
			WC_DELIVERY_DATE_VERSION,
			true
		);

		// Calculate min delivery date taking cut-off time into account
		$lead_days = absint( $options['min_lead_days'] );
		$cutoff_time = trim( $options['cutoff_time'] );
		$current_timestamp = current_time( 'timestamp' );

		if ( ! empty( $cutoff_time ) ) {
			$today_cutoff = strtotime( current_time( 'Y-m-d ' ) . $cutoff_time );
			if ( $current_timestamp > $today_cutoff ) {
				$lead_days += 1;
			}
		}

		$min_date = date( 'Y-m-d', strtotime( "+{$lead_days} days", $current_timestamp ) );
		$max_advance = absint( $options['max_advance_days'] );
		$max_date = date( 'Y-m-d', strtotime( "+{$max_advance} days", $current_timestamp ) );

		// Localize script data for calendar
		wp_localize_script(
			'wc-delivery-date-checkout-js',
			'wc_delivery_date_params',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'security'         => wp_create_nonce( 'wc-delivery-date-nonce' ),
				'min_date'         => $min_date,
				'max_date'         => $max_date,
				'allowed_weekdays' => array_map( 'intval', (array) $options['allowed_weekdays'] ),
				'blackout_dates'   => WC_Delivery_Date_AJAX::get_parsed_blackout_dates(),
				'booked_dates'     => WC_Delivery_Date_AJAX::get_fully_booked_dates(),
				'date_format'      => 'Y-m-d',
				'date_placeholder' => ! empty( $options['date_placeholder'] ) ? $options['date_placeholder'] : 'Select delivery date',
				'date_label'       => ! empty( $options['date_label'] ) && 'Delivery Date' !== $options['date_label'] ? $options['date_label'] : 'Choose Delivery Date',
				'limit_message'    => esc_js( $options['limit_reached_message'] ),
			)
		);
	}

	/**
	 * Render fields in checkout form
	 */
	public static function render_checkout_fields() {
		if ( self::$is_rendered ) {
			return;
		}

		$options = self::get_options();
		if ( empty( $options['enabled'] ) ) {
			return;
		}

		self::$is_rendered = true;

		$is_date_required = ! empty( $options['is_mandatory'] );
		$enable_time = ! empty( $options['enable_time_slot'] );
		$is_time_required = $enable_time && ! empty( $options['is_time_mandatory'] );

		// Parse time slots
		$time_slots_raw = trim( $options['time_slots'] );
		$time_slots = array();
		if ( ! empty( $time_slots_raw ) ) {
			$lines = preg_split( '/[\r\n]+/', $time_slots_raw );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( ! empty( $line ) ) {
					$time_slots[] = $line;
				}
			}
		}
		?>
		<div id="wc_delivery_date_fields_wrapper" class="wc-delivery-date-wrapper">
			<div class="wc-delivery-date-header">
				<h3><?php echo esc_html( $options['section_title'] ); ?></h3>
				<?php if ( ! empty( $options['section_description'] ) ) : ?>
					<p class="wc-delivery-date-description"><?php echo esc_html( $options['section_description'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="wc-delivery-date-fields">
				<p class="form-row form-row-wide validate-required" id="wc_delivery_date_field">
					<label for="wc_delivery_date">
						<?php echo esc_html( $options['date_label'] ); ?>
						<?php if ( $is_date_required ) : ?>
							<abbr class="required" title="<?php esc_attr_e( 'required', 'woocommerce' ); ?>">*</abbr>
						<?php endif; ?>
					</label>
					<span class="woocommerce-input-wrapper">
						<input type="text"
							class="input-text wc-delivery-date-picker"
							name="wc_delivery_date"
							id="wc_delivery_date"
							placeholder="<?php echo esc_attr( $options['date_placeholder'] ); ?>"
							readonly="readonly"
							value=""
							autocomplete="off" />
					</span>
				</p>

				<?php if ( $enable_time && ! empty( $time_slots ) ) : ?>
					<p class="form-row form-row-wide validate-required" id="wc_delivery_time_field">
						<label for="wc_delivery_time">
							<?php echo esc_html( $options['time_label'] ); ?>
							<?php if ( $is_time_required ) : ?>
								<abbr class="required" title="<?php esc_attr_e( 'required', 'woocommerce' ); ?>">*</abbr>
							<?php endif; ?>
						</label>
						<span class="woocommerce-input-wrapper">
							<select name="wc_delivery_time" id="wc_delivery_time" class="select">
								<option value=""><?php echo esc_html( $options['time_placeholder'] ); ?></option>
								<?php foreach ( $time_slots as $slot ) : ?>
									<option value="<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( $slot ); ?></option>
								<?php endforeach; ?>
							</select>
						</span>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Validate fields upon checkout submission
	 */
	public static function validate_checkout_fields() {
		$options = self::get_options();
		if ( empty( $options['enabled'] ) ) {
			return;
		}

		$date = isset( $_POST['wc_delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_delivery_date'] ) ) : '';
		$time = isset( $_POST['wc_delivery_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_delivery_time'] ) ) : '';

		// Validate mandatory date
		if ( ! empty( $options['is_mandatory'] ) && empty( $date ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: Date field label */
					__( 'Please select a %s.', 'wc-delivery-date' ),
					'<strong>' . esc_html( $options['date_label'] ) . '</strong>'
				),
				'error'
			);
			return;
		}

		// If a date is provided, validate rules
		if ( ! empty( $date ) ) {
			// Check format YYYY-MM-DD
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				wc_add_notice( __( 'Invalid delivery date format.', 'wc-delivery-date' ), 'error' );
				return;
			}

			$selected_ts = strtotime( $date );
			$current_ts = current_time( 'timestamp' );
			$lead_days = absint( $options['min_lead_days'] );

			// Check cut-off time
			$cutoff_time = trim( $options['cutoff_time'] );
			if ( ! empty( $cutoff_time ) ) {
				$today_cutoff = strtotime( current_time( 'Y-m-d ' ) . $cutoff_time );
				if ( $current_ts > $today_cutoff ) {
					$lead_days += 1;
				}
			}

			$min_allowed_ts = strtotime( date( 'Y-m-d', strtotime( "+{$lead_days} days", $current_ts ) ) );
			$max_advance = absint( $options['max_advance_days'] );
			$max_allowed_ts = strtotime( date( 'Y-m-d', strtotime( "+{$max_advance} days", $current_ts ) ) );

			if ( $selected_ts < $min_allowed_ts ) {
				wc_add_notice( __( 'The selected delivery date is earlier than the allowed preparation lead time.', 'wc-delivery-date' ), 'error' );
				return;
			}

			if ( $selected_ts > $max_allowed_ts ) {
				wc_add_notice( __( 'The selected delivery date is beyond the maximum advance booking window.', 'wc-delivery-date' ), 'error' );
				return;
			}

			// Check allowed weekdays
			$selected_weekday = (int) date( 'w', $selected_ts );
			$allowed_weekdays = array_map( 'intval', (array) $options['allowed_weekdays'] );
			if ( ! empty( $allowed_weekdays ) && ! in_array( $selected_weekday, $allowed_weekdays, true ) ) {
				wc_add_notice( __( 'Deliveries are not available on the selected day of the week.', 'wc-delivery-date' ), 'error' );
				return;
			}

			// Check blackout dates
			$blackout_dates = WC_Delivery_Date_AJAX::get_parsed_blackout_dates();
			if ( in_array( $date, $blackout_dates, true ) ) {
				wc_add_notice( __( 'Deliveries are not available on this date due to a scheduled store holiday.', 'wc-delivery-date' ), 'error' );
				return;
			}

			// Check max deliveries per day
			$max_deliveries = absint( $options['max_deliveries_per_day'] );
			if ( $max_deliveries > 0 ) {
				$current_order_count = wc_delivery_date_get_order_count_for_date( $date );
				if ( $current_order_count >= $max_deliveries ) {
					wc_add_notice( esc_html( $options['limit_reached_message'] ), 'error' );
					return;
				}
			}
		}

		// Validate mandatory time slot
		$enable_time = ! empty( $options['enable_time_slot'] );
		$is_time_required = $enable_time && ! empty( $options['is_time_mandatory'] );

		if ( $is_time_required && empty( $time ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: Time field label */
					__( 'Please select a %s.', 'wc-delivery-date' ),
					'<strong>' . esc_html( $options['time_label'] ) . '</strong>'
				),
				'error'
			);
		}
	}

	/**
	 * Save delivery date & time to order meta
	 * Compatible with HPOS and classic post meta
	 *
	 * @param WC_Order $order
	 * @param array $data
	 */
	public static function save_order_delivery_meta( $order = null, $data = array() ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$options = self::get_options();
		if ( empty( $options['enabled'] ) ) {
			return;
		}

		$raw_date = isset( $_POST['wc_delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_delivery_date'] ) ) : '';
		if ( empty( $raw_date ) && function_exists( 'WC' ) && WC()->session ) {
			$raw_date = WC()->session->get( 'wc_delivery_date' );
		}

		if ( ! empty( $raw_date ) ) {
			$order->update_meta_data( '_delivery_date', $raw_date );
			$selected_ts = strtotime( $raw_date );
			if ( $selected_ts ) {
				$formatted_date = date_i18n( get_option( 'date_format' ), $selected_ts );
				$order->update_meta_data( '_delivery_date_formatted', $formatted_date );
			}
			$order->update_meta_data( 'wc-delivery-date/delivery-date', $raw_date );
		}

		$raw_time = isset( $_POST['wc_delivery_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_delivery_time'] ) ) : '';
		if ( empty( $raw_time ) && function_exists( 'WC' ) && WC()->session ) {
			$raw_time = WC()->session->get( 'wc_delivery_time' );
		}

		if ( ! empty( $raw_time ) ) {
			$order->update_meta_data( '_delivery_time', $raw_time );
			$order->update_meta_data( 'wc-delivery-date/delivery-time', $raw_time );
		}
	}

	/**
	 * Register Additional Checkout Fields for WooCommerce Blocks (WooCommerce 8.6+)
	 */
	public static function register_blocks_checkout_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		$options = self::get_options();
		if ( empty( $options['enabled'] ) ) {
			return;
		}

		// 1. Delivery Date field for Blocks Checkout
		woocommerce_register_additional_checkout_field( array(
			'id'       => 'wc-delivery-date/delivery-date',
			'label'    => ! empty( $options['date_label'] ) && 'Delivery Date' !== $options['date_label'] ? $options['date_label'] : __( 'Choose Delivery Date', 'wc-delivery-date' ),
			'location' => 'contact', // Positions field at the very top of checkout in Contact Information section
			'type'     => 'text',
			'required' => false, // Handled server-side to prevent React validation errors with external calendar
		) );

		// 2. Delivery Time Slot field for Blocks Checkout
		if ( ! empty( $options['enable_time_slot'] ) ) {
			$time_slots_raw = trim( $options['time_slots'] );
			$options_map = array();
			$options_map[''] = ! empty( $options['time_placeholder'] ) ? $options['time_placeholder'] : __( 'Select time slot', 'wc-delivery-date' );

			if ( ! empty( $time_slots_raw ) ) {
				$lines = preg_split( '/[\r\n]+/', $time_slots_raw );
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( ! empty( $line ) ) {
						$options_map[ $line ] = $line;
					}
				}
			}

			woocommerce_register_additional_checkout_field( array(
				'id'       => 'wc-delivery-date/delivery-time',
				'label'    => ! empty( $options['time_label'] ) ? $options['time_label'] : __( 'Delivery Time', 'wc-delivery-date' ),
				'location' => 'contact', // Positions field with Delivery Date at the top of checkout
				'type'     => 'select',
				'options'  => $options_map,
				'required' => ! empty( $options['is_time_mandatory'] ),
			) );
		}
	}

	/**
	 * Save Blocks Additional Checkout Fields to Order Meta / Customer / Session
	 */
	public static function save_blocks_additional_field( $key = '', $value = '', $group = '', $order = null ) {
		if ( empty( $key ) || empty( $value ) ) {
			return;
		}

		if ( 'wc-delivery-date/delivery-date' === $key ) {
			$selected_ts = strtotime( $value );
			$sanitized_date = $selected_ts ? date( 'Y-m-d', $selected_ts ) : sanitize_text_field( $value );
			$formatted = $selected_ts ? date_i18n( get_option( 'date_format' ), $selected_ts ) : $sanitized_date;

			if ( function_exists( 'WC' ) && WC()->session ) {
				WC()->session->set( 'wc_delivery_date', $sanitized_date );
				WC()->session->set( 'wc_delivery_date_formatted', $formatted );
			}

			if ( $order instanceof WC_Data ) {
				$order->update_meta_data( '_delivery_date', $sanitized_date );
				$order->update_meta_data( '_delivery_date_formatted', $formatted );
				$order->update_meta_data( 'wc-delivery-date/delivery-date', $sanitized_date );
			}
		}

		if ( 'wc-delivery-date/delivery-time' === $key ) {
			$sanitized_time = sanitize_text_field( $value );
			if ( function_exists( 'WC' ) && WC()->session ) {
				WC()->session->set( 'wc_delivery_time', $sanitized_time );
			}
			if ( $order instanceof WC_Data ) {
				$order->update_meta_data( '_delivery_time', $sanitized_time );
				$order->update_meta_data( 'wc-delivery-date/delivery-time', $sanitized_time );
			}
		}
	}

	/**
	 * Save Store API Order Meta from Checkout Blocks
	 *
	 * @param WC_Order|null $order
	 * @param WP_REST_Request|null $request
	 */
	public static function save_store_api_order_meta( $order = null, $request = null ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$raw_date = '';
		$raw_time = '';

		// 1. Scan Store API request parameters
		if ( is_object( $request ) && method_exists( $request, 'get_params' ) ) {
			$params = $request->get_params();

			if ( ! empty( $params['additional_fields']['wc-delivery-date/delivery-date'] ) ) {
				$raw_date = $params['additional_fields']['wc-delivery-date/delivery-date'];
			} elseif ( ! empty( $params['customer']['additional_fields']['wc-delivery-date/delivery-date'] ) ) {
				$raw_date = $params['customer']['additional_fields']['wc-delivery-date/delivery-date'];
			} elseif ( ! empty( $params['checkout']['additional_fields']['wc-delivery-date/delivery-date'] ) ) {
				$raw_date = $params['checkout']['additional_fields']['wc-delivery-date/delivery-date'];
			} elseif ( ! empty( $params['wc-delivery-date/delivery-date'] ) ) {
				$raw_date = $params['wc-delivery-date/delivery-date'];
			}

			if ( ! empty( $params['additional_fields']['wc-delivery-date/delivery-time'] ) ) {
				$raw_time = $params['additional_fields']['wc-delivery-date/delivery-time'];
			} elseif ( ! empty( $params['customer']['additional_fields']['wc-delivery-date/delivery-time'] ) ) {
				$raw_time = $params['customer']['additional_fields']['wc-delivery-date/delivery-time'];
			} elseif ( ! empty( $params['checkout']['additional_fields']['wc-delivery-date/delivery-time'] ) ) {
				$raw_time = $params['checkout']['additional_fields']['wc-delivery-date/delivery-time'];
			} elseif ( ! empty( $params['wc-delivery-date/delivery-time'] ) ) {
				$raw_time = $params['wc-delivery-date/delivery-time'];
			}
		}

		// 2. Check Order's own metadata
		if ( empty( $raw_date ) ) {
			$raw_date = $order->get_meta( '_delivery_date' );
			if ( empty( $raw_date ) ) {
				$raw_date = $order->get_meta( 'wc-delivery-date/delivery-date' );
			}
		}

		// 3. Fallback to active WooCommerce session
		if ( empty( $raw_date ) && function_exists( 'WC' ) && WC()->session ) {
			$raw_date = WC()->session->get( 'wc_delivery_date' );
		}

		if ( empty( $raw_time ) && function_exists( 'WC' ) && WC()->session ) {
			$raw_time = WC()->session->get( 'wc_delivery_time' );
		}

		// 4. Fallback to WooCommerce customer object
		if ( empty( $raw_date ) && function_exists( 'WC' ) && WC()->customer ) {
			$raw_date = WC()->customer->get_meta( '_delivery_date' );
			if ( empty( $raw_date ) ) {
				$raw_date = WC()->customer->get_meta( 'wc-delivery-date/delivery-date' );
			}
		}

		// 5. Fallback to user meta if logged in
		if ( empty( $raw_date ) && $order->get_customer_id() > 0 ) {
			$raw_date = get_user_meta( $order->get_customer_id(), '_delivery_date', true );
			if ( empty( $raw_date ) ) {
				$raw_date = get_user_meta( $order->get_customer_id(), 'wc-delivery-date/delivery-date', true );
			}
		}

		if ( ! empty( $raw_date ) ) {
			$raw_date = sanitize_text_field( $raw_date );
			$selected_ts = strtotime( $raw_date );
			if ( $selected_ts ) {
				$sanitized_date = date( 'Y-m-d', $selected_ts );
				$order->update_meta_data( '_delivery_date', $sanitized_date );
				$formatted = date_i18n( get_option( 'date_format' ), $selected_ts );
				$order->update_meta_data( '_delivery_date_formatted', $formatted );
			} else {
				$order->update_meta_data( '_delivery_date', $raw_date );
			}
			$order->update_meta_data( 'wc-delivery-date/delivery-date', $raw_date );
		}

		if ( ! empty( $raw_time ) ) {
			$order->update_meta_data( '_delivery_time', sanitize_text_field( $raw_time ) );
			$order->update_meta_data( 'wc-delivery-date/delivery-time', sanitize_text_field( $raw_time ) );
		}
	}

	/**
	 * Classic checkout update order meta hook
	 *
	 * @param int $order_id
	 * @param array $data
	 */
	public static function save_order_delivery_meta_classic( $order_id, $data = array() ) {
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			self::save_order_delivery_meta( $order, $data );
			$order->save();
		}
	}

	/**
	 * Ensure Delivery Meta is fully saved on order processed hook
	 * (Fires right after checkout order creation, before payment & emails)
	 *
	 * @param int $order_id
	 * @param array $posted_data
	 * @param WC_Order|null $order
	 */
	public static function ensure_order_delivery_meta_on_process( $order_id, $posted_data = array(), $order = null ) {
		if ( ! $order && $order_id ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		self::save_store_api_order_meta( $order, null );
		$order->save();
	}

	/**
	 * Validate Blocks Additional Checkout Fields on Checkout Block Submission
	 */
	public static function validate_blocks_additional_field( $errors = null, $field_key = '', $field_value = '' ) {
		if ( ! is_wp_error( $errors ) ) {
			return;
		}
		$options = self::get_options();
		if ( empty( $options['enabled'] ) ) {
			return;
		}

		// Validate Date
		if ( 'wc-delivery-date/delivery-date' === $field_key ) {
			if ( ! empty( $options['is_mandatory'] ) && empty( $field_value ) ) {
				$errors->add(
					'delivery_date_required',
					sprintf(
						/* translators: %s: Date field label */
						__( 'Please select a %s.', 'wc-delivery-date' ),
						esc_html( $options['date_label'] )
					)
				);
				return;
			}

			if ( ! empty( $field_value ) ) {
				$selected_ts = strtotime( $field_value );
				if ( ! $selected_ts ) {
					$errors->add( 'delivery_date_invalid', __( 'Invalid delivery date format.', 'wc-delivery-date' ) );
					return;
				}

				$date_ymd = date( 'Y-m-d', $selected_ts );
				$current_ts = current_time( 'timestamp' );
				$lead_days = absint( $options['min_lead_days'] );

				$cutoff_time = trim( $options['cutoff_time'] );
				if ( ! empty( $cutoff_time ) ) {
					$today_cutoff = strtotime( current_time( 'Y-m-d ' ) . $cutoff_time );
					if ( $current_ts > $today_cutoff ) {
						$lead_days += 1;
					}
				}

				$min_allowed_ts = strtotime( date( 'Y-m-d', strtotime( "+{$lead_days} days", $current_ts ) ) );
				$max_advance = absint( $options['max_advance_days'] );
				$max_allowed_ts = strtotime( date( 'Y-m-d', strtotime( "+{$max_advance} days", $current_ts ) ) );

				if ( $selected_ts < $min_allowed_ts ) {
					$errors->add( 'delivery_date_lead_time', __( 'The selected delivery date is earlier than the allowed preparation lead time.', 'wc-delivery-date' ) );
					return;
				}

				if ( $selected_ts > $max_allowed_ts ) {
					$errors->add( 'delivery_date_max_advance', __( 'The selected delivery date is beyond the maximum advance booking window.', 'wc-delivery-date' ) );
					return;
				}

				$selected_weekday = (int) date( 'w', $selected_ts );
				$allowed_weekdays = array_map( 'intval', (array) $options['allowed_weekdays'] );
				if ( ! empty( $allowed_weekdays ) && ! in_array( $selected_weekday, $allowed_weekdays, true ) ) {
					$errors->add( 'delivery_date_weekday', __( 'Deliveries are not available on the selected day of the week.', 'wc-delivery-date' ) );
					return;
				}

				$blackout_dates = WC_Delivery_Date_AJAX::get_parsed_blackout_dates();
				if ( in_array( $field_value, $blackout_dates, true ) ) {
					$errors->add( 'delivery_date_blackout', __( 'Deliveries are not available on this date due to a scheduled store holiday.', 'wc-delivery-date' ) );
					return;
				}

				$max_deliveries = absint( $options['max_deliveries_per_day'] );
				if ( $max_deliveries > 0 ) {
					$current_order_count = wc_delivery_date_get_order_count_for_date( $field_value );
					if ( $current_order_count >= $max_deliveries ) {
						$errors->add( 'delivery_date_capacity', esc_html( $options['limit_reached_message'] ) );
						return;
					}
				}
			}
		}

		// Validate Time
		if ( 'wc-delivery-date/delivery-time' === $field_key ) {
			$enable_time = ! empty( $options['enable_time_slot'] );
			$is_time_required = $enable_time && ! empty( $options['is_time_mandatory'] );

			if ( $is_time_required && empty( $field_value ) ) {
				$errors->add(
					'delivery_time_required',
					sprintf(
						/* translators: %s: Time field label */
						__( 'Please select a %s.', 'wc-delivery-date' ),
						esc_html( $options['time_label'] )
					)
				);
			}
		}
	}
}
