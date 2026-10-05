<?php
/**
 * Admin Settings Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Delivery_Date_Settings {

	const OPTION_NAME = 'wc_delivery_date_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Register Admin Submenu under WooCommerce
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Delivery Date & Time Settings', 'wc-delivery-date' ),
			__( 'Delivery Date', 'wc-delivery-date' ),
			'manage_woocommerce',
			'wc-delivery-date-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register setting and sanitize
	 */
	public static function register_settings() {
		register_setting(
			'wc_delivery_date_settings_group',
			self::OPTION_NAME,
			array( __CLASS__, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize and validate inputs
	 */
	public static function sanitize_settings( $input ) {
		$sanitized = array();

		// General
		$sanitized['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
		$sanitized['is_mandatory'] = ! empty( $input['is_mandatory'] ) ? 1 : 0;
		$sanitized['max_deliveries_per_day'] = isset( $input['max_deliveries_per_day'] ) ? absint( $input['max_deliveries_per_day'] ) : 0;
		$sanitized['min_lead_days'] = isset( $input['min_lead_days'] ) ? absint( $input['min_lead_days'] ) : 1;
		$sanitized['max_advance_days'] = isset( $input['max_advance_days'] ) ? absint( $input['max_advance_days'] ) : 30;
		$sanitized['cutoff_time'] = ! empty( $input['cutoff_time'] ) ? sanitize_text_field( $input['cutoff_time'] ) : '';

		// Weekdays: 0 (Sun) to 6 (Sat)
		$sanitized['allowed_weekdays'] = array();
		if ( ! empty( $input['allowed_weekdays'] ) && is_array( $input['allowed_weekdays'] ) ) {
			foreach ( $input['allowed_weekdays'] as $day ) {
				$sanitized['allowed_weekdays'][] = absint( $day );
			}
		}

		// Blackout Dates / Holidays
		$sanitized['blackout_dates'] = ! empty( $input['blackout_dates'] ) ? sanitize_textarea_field( $input['blackout_dates'] ) : '';

		// Time Slot settings
		$sanitized['enable_time_slot'] = ! empty( $input['enable_time_slot'] ) ? 1 : 0;
		$sanitized['is_time_mandatory'] = ! empty( $input['is_time_mandatory'] ) ? 1 : 0;
		$sanitized['time_slots'] = ! empty( $input['time_slots'] ) ? sanitize_textarea_field( $input['time_slots'] ) : '';

		// Customer-facing Labels & Titles
		$sanitized['section_title'] = ! empty( $input['section_title'] ) ? sanitize_text_field( $input['section_title'] ) : 'Delivery Details';
		$sanitized['section_description'] = ! empty( $input['section_description'] ) ? sanitize_text_field( $input['section_description'] ) : 'Choose your preferred delivery date and time.';
		$sanitized['date_label'] = ! empty( $input['date_label'] ) ? sanitize_text_field( $input['date_label'] ) : 'Choose Delivery Date';
		$sanitized['date_placeholder'] = ! empty( $input['date_placeholder'] ) ? sanitize_text_field( $input['date_placeholder'] ) : 'Select delivery date';
		$sanitized['time_label'] = ! empty( $input['time_label'] ) ? sanitize_text_field( $input['time_label'] ) : 'Delivery Time';
		$sanitized['time_placeholder'] = ! empty( $input['time_placeholder'] ) ? sanitize_text_field( $input['time_placeholder'] ) : 'Select time slot';
		$sanitized['limit_reached_message'] = ! empty( $input['limit_reached_message'] ) ? sanitize_text_field( $input['limit_reached_message'] ) : 'Sorry, the selected delivery date has reached its maximum order capacity. Please choose another date.';

		return $sanitized;
	}

	/**
	 * Default settings values
	 */
	public static function get_defaults() {
		return array(
			'enabled'                => 1,
			'is_mandatory'           => 1,
			'max_deliveries_per_day' => 15,
			'min_lead_days'          => 1,
			'max_advance_days'       => 30,
			'cutoff_time'            => '18:00',
			'allowed_weekdays'       => array( 1, 2, 3, 4, 5 ), // Mon to Fri
			'blackout_dates'         => '',
			'enable_time_slot'       => 1,
			'is_time_mandatory'      => 1,
			'time_slots'             => "09:00 AM - 12:00 PM\n12:00 PM - 03:00 PM\n03:00 PM - 06:00 PM",
			'section_title'          => 'Delivery Details',
			'section_description'    => 'Choose your preferred delivery date and time.',
			'date_label'             => 'Choose Delivery Date',
			'date_placeholder'       => 'Select delivery date',
			'time_label'             => 'Delivery Time',
			'time_placeholder'       => 'Select time slot',
			'limit_reached_message'  => 'Sorry, the selected delivery date has reached its maximum order capacity. Please choose another date.',
		);
	}

	/**
	 * Enqueue admin scripts & styles
	 */
	public static function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-delivery-date-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wc-delivery-date-admin-css',
			WC_DELIVERY_DATE_URL . 'assets/css/admin.css',
			array(),
			WC_DELIVERY_DATE_VERSION
		);
	}

	/**
	 * Render Settings Page HTML
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$options = wp_parse_args( get_option( self::OPTION_NAME, array() ), self::get_defaults() );
		$weekdays = array(
			0 => __( 'Sunday', 'wc-delivery-date' ),
			1 => __( 'Monday', 'wc-delivery-date' ),
			2 => __( 'Tuesday', 'wc-delivery-date' ),
			3 => __( 'Wednesday', 'wc-delivery-date' ),
			4 => __( 'Thursday', 'wc-delivery-date' ),
			5 => __( 'Friday', 'wc-delivery-date' ),
			6 => __( 'Saturday', 'wc-delivery-date' ),
		);
		?>
		<div class="wrap wc-delivery-date-admin-wrap">
			<div class="wc-dd-header-area">
				<h1>
					<?php esc_html_e( 'WooCommerce Delivery Date & Time Settings', 'wc-delivery-date' ); ?>
					<span class="wc-dd-version-badge">v<?php echo esc_html( WC_DELIVERY_DATE_VERSION ); ?></span>
				</h1>
			</div>
			<p class="description">
				<?php esc_html_e( 'Configure delivery availability, scheduling limits, order capacity per day, and customer checkout labels.', 'wc-delivery-date' ); ?>
			</p>

			<form method="post" action="options.php" class="wc-delivery-date-form">
				<?php
				settings_fields( 'wc_delivery_date_settings_group' );
				?>

				<!-- CARD 1: GENERAL SCHEDULE CONFIGURATION -->
				<div class="wc-dd-card">
					<h2><?php esc_html_e( '1. Delivery Rules & Capacity Limits', 'wc-delivery-date' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Delivery Scheduler', 'wc-delivery-date' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $options['enabled'], 1 ); ?> />
									<?php esc_html_e( 'Enable delivery date and time selection at checkout', 'wc-delivery-date' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Require Date at Checkout', 'wc-delivery-date' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[is_mandatory]" value="1" <?php checked( $options['is_mandatory'], 1 ); ?> />
									<?php esc_html_e( 'Make selecting a delivery date mandatory for the customer', 'wc-delivery-date' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'If checked, customers cannot complete checkout without selecting a valid date.', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Maximum Deliveries Per Day', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="number" min="0" step="1" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_deliveries_per_day]" value="<?php echo esc_attr( $options['max_deliveries_per_day'] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Maximum number of orders allowed for a single delivery day. Set to 0 for unlimited. Once reached, the day is automatically disabled in the calendar.', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Minimum Lead Time (Days)', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="number" min="0" step="1" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[min_lead_days]" value="<?php echo esc_attr( $options['min_lead_days'] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Number of preparation days required after purchase before delivery can take place (0 = Same day delivery allowed, 1 = Next day, 2 = 2 days after, etc.).', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Maximum Advance Booking (Days)', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="number" min="1" step="1" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_advance_days]" value="<?php echo esc_attr( $options['max_advance_days'] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'How many days into the future customers are allowed to schedule delivery (e.g. 30 days).', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Daily Cut-off Time', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="time" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[cutoff_time]" value="<?php echo esc_attr( $options['cutoff_time'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Orders placed after this time will automatically add +1 day to the minimum preparation lead time (e.g., 18:00).', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Allowed Delivery Weekdays', 'wc-delivery-date' ); ?></th>
							<td>
								<fieldset>
									<?php foreach ( $weekdays as $day_index => $day_name ) : ?>
										<label style="display: inline-block; margin-right: 15px;">
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[allowed_weekdays][]" value="<?php echo esc_attr( $day_index ); ?>" <?php checked( in_array( $day_index, (array) $options['allowed_weekdays'] ) ); ?> />
											<?php echo esc_html( $day_name ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Only checked days will be selectable by customers in the checkout calendar.', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Blackout Dates & Holidays', 'wc-delivery-date' ); ?></th>
							<td>
								<textarea name="<?php echo esc_attr( self::OPTION_NAME ); ?>[blackout_dates]" rows="4" cols="50" class="large-text code" placeholder="2026-12-25&#10;2027-01-01"><?php echo esc_textarea( $options['blackout_dates'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Specify dates when deliveries are not available (e.g. store holidays). Enter one date per line or separated by commas in YYYY-MM-DD format.', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- CARD 2: TIME SLOTS -->
				<div class="wc-dd-card">
					<h2><?php esc_html_e( '2. Delivery Time Slots', 'wc-delivery-date' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Time Slot Selection', 'wc-delivery-date' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_time_slot]" value="1" <?php checked( $options['enable_time_slot'], 1 ); ?> />
									<?php esc_html_e( 'Allow customers to pick a specific time slot for delivery', 'wc-delivery-date' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Require Time Slot', 'wc-delivery-date' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[is_time_mandatory]" value="1" <?php checked( $options['is_time_mandatory'], 1 ); ?> />
									<?php esc_html_e( 'Make time slot selection mandatory', 'wc-delivery-date' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Available Time Slots', 'wc-delivery-date' ); ?></th>
							<td>
								<textarea name="<?php echo esc_attr( self::OPTION_NAME ); ?>[time_slots]" rows="5" cols="50" class="large-text" placeholder="09:00 AM - 12:00 PM&#10;12:00 PM - 03:00 PM&#10;03:00 PM - 06:00 PM"><?php echo esc_textarea( $options['time_slots'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Enter one time slot per line. Example: 09:00 AM - 12:00 PM', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- CARD 3: CUSTOMER-FACING LABELS AND TEXTS -->
				<div class="wc-dd-card">
					<h2><?php esc_html_e( '3. Customer-Facing Labels & Texts', 'wc-delivery-date' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Checkout Section Title', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[section_title]" value="<?php echo esc_attr( $options['section_title'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Heading displayed above the delivery fields at checkout.', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Section Description / Subtitle', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[section_description]" value="<?php echo esc_attr( $options['section_description'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Short informational text beneath the section title.', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Date Field Label', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[date_label]" value="<?php echo esc_attr( $options['date_label'] ); ?>" class="regular-text" />
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Date Field Placeholder', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[date_placeholder]" value="<?php echo esc_attr( $options['date_placeholder'] ); ?>" class="regular-text" />
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Time Slot Label', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[time_label]" value="<?php echo esc_attr( $options['time_label'] ); ?>" class="regular-text" />
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Time Slot Placeholder', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[time_placeholder]" value="<?php echo esc_attr( $options['time_placeholder'] ); ?>" class="regular-text" />
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Capacity Exceeded Error Message', 'wc-delivery-date' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[limit_reached_message]" value="<?php echo esc_attr( $options['limit_reached_message'] ); ?>" class="large-text" />
								<p class="description"><?php esc_html_e( 'Notice shown if the customer attempts to submit checkout with a fully-booked date.', 'wc-delivery-date' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Save Delivery Settings', 'wc-delivery-date' ) ); ?>
			</form>

			<!-- CARD 4: VERSION HISTORY & CHANGELOG -->
			<div class="wc-dd-card wc-dd-changelog-card">
				<h2>
					<?php esc_html_e( '4. Version History & Changelog', 'wc-delivery-date' ); ?>
					<span class="wc-dd-current-tag"><?php printf( esc_html__( 'Current: v%s', 'wc-delivery-date' ), esc_html( WC_DELIVERY_DATE_VERSION ) ); ?></span>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Chronological record of releases, updates, and feature additions for this plugin.', 'wc-delivery-date' ); ?>
				</p>

				<div class="wc-dd-timeline">
					<?php foreach ( self::get_changelog() as $version => $release ) : ?>
						<div class="wc-dd-timeline-item">
							<div class="wc-dd-timeline-header">
								<span class="wc-dd-version-pill">v<?php echo esc_html( $version ); ?></span>
								<strong class="wc-dd-release-title"><?php echo esc_html( $release['title'] ); ?></strong>
								<span class="wc-dd-release-date"><?php echo esc_html( $release['date'] ); ?></span>
							</div>
							<ul class="wc-dd-changes-list">
								<?php foreach ( $release['changes'] as $change ) : ?>
									<li><?php echo esc_html( $change ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Chronological list of plugin releases and changes
	 *
	 * @return array
	 */
	public static function get_changelog() {
		return array(
			'1.1.7' => array(
				'date'    => '2026-10-04',
				'title'   => 'Transactional Emails Delivery Schedule Reliability',
				'changes' => array(
					'Fixed missing delivery date in customer and admin transactional emails.',
					'Added multi-layer persistence saving delivery details in WooCommerce session, order meta, and Store API hooks.',
					'Hooked woocommerce_checkout_order_processed ensuring delivery data is saved before payment & emails.',
					'Fixed per-email deduplication key allowing both customer and admin emails in same checkout session.',
					'Added woocommerce_email_customer_details fallback hook for custom email designer templates.',
				),
			),
			'1.1.6' => array(
				'date'    => '2026-10-04',
				'title'   => 'Store API Hook Parameters & Critical Error Fix',
				'changes' => array(
					'Fixed critical error on checkout page caused by argument count mismatch in Store API draft order hooks.',
					'Added strict default parameter guards and null safety across all Store API, email, and order hooks.',
					'Prevented premature order meta save calls on unpersisted draft checkout objects.',
				),
			),
			'1.1.5' => array(
				'date'    => '2026-10-04',
				'title'   => 'Transactional Emails & Order Meta Persistence Enhancement',
				'changes' => array(
					'Added delivery date & time schedule to all customer & admin transactional emails.',
					'Integrated woocommerce_email_order_meta_fields filter and woocommerce_email_order_meta fallback hook.',
					'Added Store API order meta synchronization (woocommerce_store_api_checkout_update_order_meta).',
					'Added multi-key metadata fallback ensuring delivery details are retrieved across all storage mechanisms.',
				),
			),
			'1.1.4' => array(
				'date'    => '2026-10-04',
				'title'   => 'Calendar Interaction & Delegated Initialization Fix',
				'changes' => array(
					'Resolved calendar opening issue on click by eliminating JavaScript syntax error.',
					'Added global delegated click/focus handler ensuring calendar opens instantly under all React re-renders.',
					'Extended mounting polling timer to 7.5 seconds across React hydration lifecycle.',
					'Suppressed "(optional)" suffix from title label for a clean "Choose Delivery Date" header.',
				),
			),
			'1.1.3' => array(
				'date'    => '2026-10-04',
				'title'   => 'Checkout Form Top Placement & Date Selection Persistence',
				'changes' => array(
					'Relocated Delivery Date & Time fields to Contact Information section at the very top of checkout.',
					'Fixed selected date persistence; formatted date remains visible upon calendar closure without reverting to placeholder.',
					'Added strict blur and input value protection against React re-render wiping.',
					'Added classic checkout top hook (woocommerce_before_checkout_form).',
				),
			),
			'1.1.2' => array(
				'date'    => '2026-10-04',
				'title'   => 'Checkout Blocks Validation & Position Enhancement',
				'changes' => array(
					'Fixed "Please enter a valid delivery date" error on WooCommerce Checkout Blocks.',
					'Updated field title to "Choose Delivery Date" with prominent 20px typography.',
					'Removed pre-selected date by default; input starts clean with placeholder until user selects a date.',
					'Positioned delivery date section at the very top of the checkout form above all fields and payment options.',
				),
			),
			'1.1.1' => array(
				'date'    => '2026-10-04',
				'title'   => 'Stripe & Checkout Blocks Stability Fix',
				'changes' => array(
					'Resolved browser unresponsiveness (close or wait) by eliminating recursive MutationObserver loops.',
					'Fixed payment gateway mounting (Stripe and express checkout methods now load cleanly).',
					'Switched checkout block reordering to native CSS Flexbox order for maximum React stability.',
					'Ensured selected delivery date persistence without React state wiping.',
				),
			),
			'1.1.0' => array(
				'date'    => '2026-10-03',
				'title'   => 'Checkout Blocks UI & Navigation Enhancement',
				'changes' => array(
					'Positioned "Select Delivery Date" field label clearly outside and above the selection box.',
					'Fixed selected date persistence in WooCommerce Checkout Blocks (Gutenberg) to avoid placeholder overlap.',
					'Fixed calendar month initialization and month navigation for future advance bookings.',
					'Enhanced layout positioning immediately above Payment Options across all checkout modes.',
					'Updated plugin author to P!xel Web.',
				),
			),
			'1.0.0' => array(
				'date'    => '2026-10-03',
				'title'   => 'Initial Production Release',
				'changes' => array(
					'Interactive Flatpickr datepicker & time slot selector added to WooCommerce checkout.',
					'Daily delivery capacity management with automatic blocking of fully booked dates.',
					'Configurable preparation lead time (minimum days) and maximum advance booking limits.',
					'Daily cut-off time support (automatically adds +1 day to minimum lead time after specified hour).',
					'Allowed delivery weekdays selection (Monday to Sunday checkboxes).',
					'Store blackout dates & holidays blocking (custom dates list).',
					'Fully customizable customer-facing labels, titles, and capacity warning messages.',
					'Delivery schedule displayed in Customer Thank You page and My Account > View Order.',
					'Delivery schedule displayed in HTML and Plain Text WooCommerce transactional emails (Customer & Admin).',
					'Custom "Delivery Schedule" column added to WooCommerce Admin Orders list with calendar/time icons.',
					'Directly editable delivery schedule fields in Admin Order Details screen.',
					'Full compatibility with WooCommerce HPOS (High-Performance Order Storage) and classic posts table.',
				),
			),
		);
	}
}
