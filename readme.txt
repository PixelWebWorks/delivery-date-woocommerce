=== WooCommerce Delivery Date & Time Scheduler ===
Contributors: pixel-web
Tags: woocommerce, delivery date, delivery time, shipping, checkout
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allows customers to select delivery date and time at checkout with admin daily capacity limits, preparation lead time, allowed weekdays, and email notifications.

== Description ==

WooCommerce Delivery Date & Time Scheduler is a lightweight, high-performance plugin designed to give online stores complete control over delivery logistics and order fulfillment.

Features include:
* Interactive calendar datepicker (Flatpickr) and time slot dropdown at checkout.
* Maximum deliveries per day order capacity limit with real-time automatic booking blocking.
* Preparation lead time (minimum days between purchase and delivery).
* Daily cut-off time (e.g. after 18:00 add +1 day to lead time).
* Maximum advance booking days limit.
* Allowed delivery weekdays selection (Monday to Sunday).
* Store blackout dates & holidays blocking.
* Custom labels and headings configurable in English.
* Delivery schedule displayed in Customer Thank You page and My Account.
* Delivery details sent in WooCommerce Customer and Admin transactional emails.
* Dedicated "Delivery Schedule" column in WooCommerce Orders list.
* Editable delivery schedule inside Admin Order Details screen.
* High-Performance Order Storage (HPOS) compatible.

== Installation ==

1. Upload the `wc-delivery-date` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your rules under WooCommerce > Delivery Date.

== Changelog ==

= 1.1.8 - 2026-10-06 =
* Added native YayMail email customizer integration with custom shortcodes ([yaymail_custom_shortcode_delivery_date], [yaymail_custom_shortcode_delivery_details], [yaymail_custom_shortcode_delivery_time]).
* Fixed duplicate delivery date output in default WooCommerce transactional emails by removing redundant email meta fields hook.
* Added standard shortcodes [wc_delivery_date], [wc_delivery_time], and [wc_delivery_details] for full compatibility across all email and page builders.
* Added live preview fallback support for visual email builder editors when designing templates.

= 1.1.7 - 2026-10-04 =
* Fixed missing delivery date in customer and admin transactional emails.
* Added multi-layer persistence saving delivery details in WooCommerce session, order meta, and Store API hooks.
* Added woocommerce_checkout_order_processed listener ensuring delivery data is saved before payment & emails.
* Fixed per-email deduplication key allowing both customer and admin emails in same checkout session.
* Added woocommerce_email_customer_details fallback hook for custom email designer templates.

= 1.1.6 - 2026-10-04 =
* Fixed critical error on checkout page caused by argument count mismatch in Store API draft order hooks.
* Strengthened callback parameter defaults and null checks across all Store API, email, and order hooks.
* Removed premature order meta persistence calls on unpersisted draft checkout objects.

= 1.1.5 - 2026-10-04 =
* Added delivery date & time schedule to all customer & admin transactional emails.
* Integrated woocommerce_email_order_meta_fields filter and woocommerce_email_order_meta fallback hook.
* Added Store API order meta synchronization (woocommerce_store_api_checkout_update_order_meta).
* Added multi-key metadata fallback ensuring delivery details are retrieved across all storage mechanisms.

= 1.1.4 - 2026-10-04 =
* Resolved calendar opening issue on click by eliminating JavaScript syntax error.
* Added global delegated click/focus handler ensuring calendar opens instantly under all React re-renders.
* Extended mounting polling timer to 7.5 seconds across React hydration lifecycle.
* Suppressed "(optional)" suffix from title label for a clean "Choose Delivery Date" header.

= 1.1.3 - 2026-10-04 =
* Relocated Delivery Date & Time fields to Contact Information section at the very top of checkout.
* Fixed selected date persistence; formatted date remains visible upon calendar closure without reverting to placeholder.
* Added strict blur and input value protection against React re-render wiping.
* Added classic checkout top hook (woocommerce_before_checkout_form).

= 1.1.2 - 2026-10-04 =
* Fixed "Please enter a valid delivery date" validation issue in WooCommerce Checkout Blocks.
* Updated field title to "Choose Delivery Date" with prominent 20px typography.
* Removed pre-selected date by default; input starts clean with placeholder until user selects a date.
* Positioned delivery date section at the very top of the checkout form above all fields and payment options.

= 1.1.1 - 2026-10-04 =
* Resolved browser unresponsiveness (close or wait) by eliminating recursive MutationObserver loops.
* Fixed payment gateway mounting (Stripe and express checkout methods now load cleanly).
* Switched checkout block reordering to native CSS Flexbox order for maximum React stability.
* Ensured selected delivery date persistence without React state wiping.

= 1.1.0 - 2026-10-03 =
* Moved "Select Delivery Date" field label clearly outside and above the selection box.
* Fixed selected date persistence in WooCommerce Checkout Blocks (Gutenberg) to avoid placeholder overlap.
* Fixed calendar month initialization and month navigation for future advance bookings.
* Enhanced layout positioning immediately above Payment Options across all checkout modes.
* Updated plugin author to P!xel Web.

= 1.0.0 - 2026-10-03 =
* Initial official production release.
* Added interactive Flatpickr datepicker & time slot dropdown to checkout.
* Added daily order capacity limit with real-time slot checking.
* Added minimum lead time (preparation days) and maximum advance booking settings.
* Added daily cut-off time (automatically adds +1 day after cut-off hour).
* Added allowed delivery weekdays selection (Monday through Sunday).
* Added store blackout dates & holiday blocking.
* Added customizable customer-facing labels, titles, and capacity warning messages.
* Added delivery schedule display in Customer Thank You page and My Account > View Order.
* Added delivery schedule display in HTML and Plain Text transactional emails (Customer & Admin).
* Added custom "Delivery Schedule" column to WooCommerce Admin Orders list.
* Added editable delivery fields in WooCommerce Admin Order Details.
* Added full compatibility with WooCommerce High-Performance Order Storage (HPOS).
