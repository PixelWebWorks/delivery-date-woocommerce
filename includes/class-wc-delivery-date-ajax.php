<?php
/**
 * Delivery Date AJAX handler & availability helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Delivery_Date_AJAX {

	public static function init() {
		add_action( 'wp_ajax_wc_delivery_date_check_availability', array( __CLASS__, 'check_availability' ) );
		add_action( 'wp_ajax_nopriv_wc_delivery_date_check_availability', array( __CLASS__, 'check_availability' ) );
		add_action( 'wp_ajax_wc_delivery_date_save_time_slot', array( __CLASS__, 'save_time_slot' ) );
		add_action( 'wp_ajax_nopriv_wc_delivery_date_save_time_slot', array( __CLASS__, 'save_time_slot' ) );
	}

	/**
	 * AJAX endpoint to check availability for a date
	 */
	public static function check_availability() {
		check_ajax_referer( 'wc-delivery-date-nonce', 'security' );

		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		if ( empty( $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date provided.', 'wc-delivery-date' ) ) );
		}

		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );
		$max_deliveries = absint( $options['max_deliveries_per_day'] );

		if ( $max_deliveries > 0 ) {
			$count = wc_delivery_date_get_order_count_for_date( $date );
			if ( $count >= $max_deliveries ) {
				wp_send_json_error( array(
					'available' => false,
					'message'   => $options['limit_reached_message'],
					'count'     => $count,
					'limit'     => $max_deliveries,
				) );
			}
		}

		// Persist valid delivery date in WooCommerce session immediately
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'wc_delivery_date', $date );
			$formatted = date_i18n( get_option( 'date_format' ), strtotime( $date ) );
			WC()->session->set( 'wc_delivery_date_formatted', $formatted );
		}

		wp_send_json_success( array(
			'available' => true,
		) );
	}

	/**
	 * AJAX endpoint to store selected time slot in session
	 */
	public static function save_time_slot() {
		check_ajax_referer( 'wc-delivery-date-nonce', 'security' );
		$time = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'wc_delivery_time', $time );
		}
		wp_send_json_success();
	}

	/**
	 * Calculate list of dates that have reached the maximum deliveries limit
	 * within the active booking window.
	 *
	 * @return array Array of Y-m-d dates that are fully booked
	 */
	public static function get_fully_booked_dates() {
		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );
		$max_deliveries = absint( $options['max_deliveries_per_day'] );

		if ( $max_deliveries <= 0 ) {
			return array();
		}

		$start_date = current_time( 'Y-m-d' );
		$max_advance = absint( $options['max_advance_days'] );
		$end_timestamp = strtotime( "+{$max_advance} days", strtotime( $start_date ) );
		$end_date = date( 'Y-m-d', $end_timestamp );

		// Query active orders within range that have a delivery date
		$orders = wc_get_orders( array(
			'limit'        => -1,
			'status'       => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' ),
			'return'       => 'ids',
			'meta_query'   => array(
				array(
					'key'     => '_delivery_date',
					'value'   => array( $start_date, $end_date ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
		) );

		if ( empty( $orders ) ) {
			return array();
		}

		// Count occurrences per date
		$date_counts = array();
		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$del_date = $order->get_meta( '_delivery_date' );
			if ( ! empty( $del_date ) ) {
				if ( ! isset( $date_counts[ $del_date ] ) ) {
					$date_counts[ $del_date ] = 0;
				}
				$date_counts[ $del_date ]++;
			}
		}

		$fully_booked = array();
		foreach ( $date_counts as $d => $c ) {
			if ( $c >= $max_deliveries ) {
				$fully_booked[] = $d;
			}
		}

		return $fully_booked;
	}

	/**
	 * Parse blackout dates into array of Y-m-d
	 */
	public static function get_parsed_blackout_dates() {
		$options = wp_parse_args( get_option( WC_Delivery_Date_Settings::OPTION_NAME, array() ), WC_Delivery_Date_Settings::get_defaults() );
		$raw = trim( $options['blackout_dates'] );
		if ( empty( $raw ) ) {
			return array();
		}

		// Split by lines or commas
		$lines = preg_split( '/[\r\n,]+/', $raw );
		$dates = array();
		foreach ( $lines as $line ) {
			$clean = trim( $line );
			if ( ! empty( $clean ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $clean ) ) {
				$dates[] = $clean;
			}
		}

		return array_values( array_unique( $dates ) );
	}
}
