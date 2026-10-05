/**
 * Delivery Date & Time Checkout Frontend Controller
 * Supports both WooCommerce Classic Checkout & modern WooCommerce Checkout Blocks
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		// Run initial check
		initDeliveryDatePicker();

		// Poll every 300ms for 25 attempts (7.5s) to guarantee attachment across React hydration & re-renders
		var attempts = 0;
		var pollTimer = setInterval(function () {
			attempts++;
			initDeliveryDatePicker();
			if (attempts >= 25) {
				clearInterval(pollTimer);
			}
		}, 300);

		// Global delegated click/focus: guarantees calendar opens even if clicked immediately upon mounting
		$(document).on('click focus', '#wc_delivery_date, input[id*="delivery-date"], input[name*="delivery-date"], .wc-dd-input', function () {
			var el = this;
			if (!el._flatpickr) {
				initDeliveryDatePicker();
			}
			if (el._flatpickr && !el._flatpickr.isOpen) {
				el._flatpickr.open();
			}
		});

		// Re-initialize if classic WooCommerce checkout updates fragments via AJAX
		$(document.body).on('updated_checkout', function () {
			initDeliveryDatePicker();
		});

		// Save time slot selection to session via AJAX
		$(document).on('change', '#wc_delivery_time, select[id*="delivery-time"], select[name*="delivery-time"]', function () {
			var timeVal = $(this).val();
			if (typeof wc_delivery_date_params !== 'undefined') {
				$.ajax({
					url: wc_delivery_date_params.ajax_url,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'wc_delivery_date_save_time_slot',
						security: wc_delivery_date_params.security,
						time: timeVal
					}
				});
			}
		});
	});

	/**
	 * Helper to update React synthetic input values and trigger state in React 16/17/18
	 */
	function setNativeValue(element, value) {
		if (!element) {
			return;
		}

		try {
			// React 16/17/18 internal Value Tracker
			var tracker = element._valueTracker;
			if (tracker) {
				tracker.setValue(''); // Force React tracker to acknowledge change
			}

			var prototype = Object.getPrototypeOf(element);
			var prototypeValueSetter = Object.getOwnPropertyDescriptor(prototype, 'value');
			if (prototypeValueSetter && prototypeValueSetter.set) {
				prototypeValueSetter.set.call(element, value);
			} else {
				element.value = value;
			}

			element.dispatchEvent(new Event('input', { bubbles: true }));
			element.dispatchEvent(new Event('change', { bubbles: true }));
		} catch (e) {
			element.value = value;
		}
	}

	/**
	 * Parse YYYY-MM-DD string into a valid local Date object
	 */
	function parseYMD(dateStr) {
		if (!dateStr || typeof dateStr !== 'string') {
			return null;
		}
		var parts = dateStr.split('-');
		if (parts.length !== 3) {
			return null;
		}
		return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
	}

	/**
	 * Format Date object to YYYY-MM-DD
	 */
	function formatDate(d) {
		var year = d.getFullYear();
		var month = ('0' + (d.getMonth() + 1)).slice(-2);
		var day = ('0' + d.getDate()).slice(-2);
		return year + '-' + month + '-' + day;
	}

	function initDeliveryDatePicker() {
		if (typeof wc_delivery_date_params === 'undefined') {
			return false;
		}

		// Find either Classic input or Blocks additional field input (target original input, not altInput)
		var selectors = [
			'#wc_delivery_date',
			'input[id*="delivery-date"]',
			'input[name*="delivery-date"]'
		];

		var $dateInput = $(selectors.join(', ')).filter(':not(.flatpickr-input)').first();
		if (!$dateInput.length) {
			$dateInput = $(selectors.join(', ')).first();
		}
		if (!$dateInput.length) {
			return false;
		}

		var inputElement = $dateInput[0];

		// Avoid double initialization on the exact same DOM node
		if (inputElement._flatpickr) {
			return true;
		}

		var params = wc_delivery_date_params;
		var labelTitle = params.date_label || 'Choose Delivery Date';
		var placeholderText = params.date_placeholder || 'Select delivery date';

		// Set placeholder on input right away
		$dateInput.attr('placeholder', placeholderText);

		// Configure label OUTSIDE and ABOVE the box
		var $wrapper = $dateInput.closest('.wc-block-components-text-input, .form-row');
		var $label = $wrapper.find('label');
		if ($label.length) {
			$label.text(labelTitle).css({
				'font-size': '20px',
				'font-weight': '600',
				'display': 'block',
				'margin-bottom': '10px'
			}).show();
		}

		var allowedWeekdays = params.allowed_weekdays || [1, 2, 3, 4, 5];
		var blackoutDates = params.blackout_dates || [];
		var bookedDates = params.booked_dates || [];

		// Parse min and max dates into JavaScript Date objects
		var minDateObj = parseYMD(params.min_date) || new Date();
		var maxDateObj = parseYMD(params.max_date) || null;

		// Check if Flatpickr library is available
		if (typeof flatpickr !== 'undefined') {
			var fpInstance = flatpickr(inputElement, {
				dateFormat: 'Y-m-d',
				altInput: true,
				altFormat: 'F j, Y', // Displays e.g. "October 12, 2026"
				altInputClass: 'wc-block-components-text-input__input flatpickr-input wc-dd-input',
				minDate: minDateObj,
				maxDate: maxDateObj,
				defaultDate: null,    // Do not pre-select any date by default
				disableMobile: false,
				disable: [
					function (date) {
						var dateString = formatDate(date);
						var dayOfWeek = date.getDay(); // 0 is Sunday, 6 is Saturday

						// 1. Check if weekday is allowed
						if (allowedWeekdays.indexOf(dayOfWeek) === -1) {
							return true; // disabled
						}

						// 2. Check if date is in store holidays / blackout dates
						if (blackoutDates.indexOf(dateString) !== -1) {
							return true; // disabled
						}

						// 3. Check if date has reached maximum order capacity
						if (bookedDates.indexOf(dateString) !== -1) {
							return true; // disabled
						}

						return false; // date is available
					}
				],
				onReady: function (selectedDates, dateStr, instance) {
					if (instance.altInput) {
						instance.altInput.setAttribute('placeholder', placeholderText);
						instance.altInput.setAttribute('autocomplete', 'off');
						if (!selectedDates.length) {
							instance.altInput.value = '';
						}
					}

					if ($label.length) {
						$label.text(labelTitle).css({
							'font-size': '20px',
							'font-weight': '600',
							'display': 'block',
							'margin-bottom': '10px'
						}).show();
					}
				},
				onOpen: function (selectedDates, dateStr, instance) {
					// Navigate calendar to allowed booking window month if no date is picked yet
					if (!selectedDates.length && minDateObj) {
						instance.jumpToDate(minDateObj);
					}
				},
				onChange: function (selectedDates, dateStr, instance) {
					if (!dateStr || !selectedDates.length) {
						if (instance.altInput) {
							instance.altInput.value = '';
						}
						setNativeValue(inputElement, '');
						return;
					}

					var formattedDate = instance.formatDate(selectedDates[0], 'F j, Y');
					if (instance.altInput) {
						instance.altInput.value = formattedDate;
					}

					// Update React synthetic value on real input
					setNativeValue(inputElement, dateStr);

					var dateForAjax = formatDate(selectedDates[0]);

					// Verify date availability via AJAX in real-time
					$.ajax({
						url: params.ajax_url,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'wc_delivery_date_check_availability',
							security: params.security,
							date: dateForAjax
						},
						success: function (response) {
							if (response && !response.success) {
								alert(response.data.message || params.limit_message);
								instance.clear();
								if (instance.altInput) {
									instance.altInput.value = '';
								}
								setNativeValue(inputElement, '');
							}
						}
					});
				},
				onClose: function (selectedDates, dateStr, instance) {
					if (selectedDates && selectedDates.length && instance.altInput) {
						instance.altInput.value = instance.formatDate(selectedDates[0], 'F j, Y');
						setNativeValue(inputElement, dateStr);
					}
				}
			});

			if (fpInstance && fpInstance.altInput) {
				// Prevent any external blur or click event from wiping the chosen date
				$(fpInstance.altInput).on('blur change input', function () {
					if (fpInstance.selectedDates && fpInstance.selectedDates.length) {
						var formatted = fpInstance.formatDate(fpInstance.selectedDates[0], 'F j, Y');
						if (this.value !== formatted) {
							this.value = formatted;
						}
					}
				});
			}
		} else {
			// Native browser fallback if Flatpickr CDN is unavailable
			$dateInput.attr('type', 'date');
			$dateInput.attr('min', params.min_date);
			$dateInput.attr('max', params.max_date);
			$dateInput.attr('placeholder', placeholderText);
			$dateInput.removeAttr('readonly');

			$dateInput.on('change', function () {
				var val = $(this).val();
				if (!val) {
					setNativeValue(inputElement, '');
					return;
				}

				var parts = val.split('-');
				var d = new Date(parts[0], parts[1] - 1, parts[2]);
				var dayOfWeek = d.getDay();

				if (allowedWeekdays.indexOf(dayOfWeek) === -1 || blackoutDates.indexOf(val) !== -1 || bookedDates.indexOf(val) !== -1) {
					alert(params.limit_message || 'The selected date is not available for delivery. Please choose another date.');
					$(this).val('');
					setNativeValue(inputElement, '');
				} else {
					setNativeValue(inputElement, val);
				}
			});
		}

		return true;
	}

})(jQuery);
