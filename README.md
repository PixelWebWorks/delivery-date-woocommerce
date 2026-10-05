# WooCommerce Delivery Date & Time Scheduler

A modern, high-performance WooCommerce plugin that allows customers to select their preferred delivery date and time slot at checkout, while giving store administrators full control over daily delivery capacities, lead times, allowed weekdays, and customizable titles.

---

## 🌟 Key Features

1. **Checkout Date & Time Selector**:
   - Modern, responsive calendar date picker (powered by Flatpickr with native fallback).
   - Dropdown selector for delivery time slots.
   - Real-time capacity and availability checking.

2. **Daily Capacity Limits**:
   - Set maximum deliveries per day (e.g. 15 deliveries per day).
   - Once a date reaches its order limit, it is automatically blocked in the calendar and rejected at checkout.

3. **Preparation Lead Time & Advance Limits**:
   - Define minimum lead days after purchase before delivery can be requested (e.g., `0` for same-day, `1` for next-day, `2` for 2 days ahead, etc.).
   - Define maximum advance booking window (e.g., up to 30 or 60 days into the future).
   - Daily cut-off time (e.g., `18:00` / 6:00 PM). If an order is placed after this hour, an extra preparation day is automatically added.

4. **Allowed Weekdays & Holidays**:
   - Choose which days of the week deliveries are made (Sunday through Saturday checkboxes).
   - Blackout dates / store holidays list (YYYY-MM-DD format).

5. **Mandatory or Optional**:
   - Toggle whether date selection is strictly required or optional.
   - Toggle whether time slot selection is required or optional.

6. **Fully Customizable Labels (in English)**:
   - Section Title (default: *Delivery Details*)
   - Section Description / Subtitle
   - Date Field Label & Placeholder
   - Time Slot Label & Placeholder
   - Capacity Exceeded error message

7. **Orders & Email Notifications**:
   - Displayed on the customer's **Thank You** (Order Received) page and **My Account > View Order**.
   - Included in **WooCommerce transactional emails** (both customer order confirmations and admin new order notifications).
   - Custom column **Delivery Schedule** in WooCommerce Admin Orders list (with calendar icon and time).
   - Editable directly in WooCommerce Admin Order Edit screen.

8. **HPOS Compatible**:
   - Fully compatible with WooCommerce High-Performance Order Storage (Custom Order Tables) and classic posts table.

---

## 📂 Plugin Structure

```
wc-delivery-date/
├── wc-delivery-date.php                      # Main plugin bootstrap & HPOS declaration
├── README.md                                 # Documentation & usage guide
├── includes/
│   ├── class-wc-delivery-date-settings.php   # Admin settings page (under WooCommerce > Delivery Date)
│   ├── class-wc-delivery-date-checkout.php   # Checkout fields, asset enqueue & validation
│   ├── class-wc-delivery-date-orders.php     # Admin order column, order edit & customer account display
│   ├── class-wc-delivery-date-emails.php     # HTML & plain text transactional email hooks
│   └── class-wc-delivery-date-ajax.php       # Real-time availability AJAX handler & capacity counter
└── assets/
    ├── css/
    │   ├── admin.css                         # Admin settings layout styles
    │   └── checkout.css                      # Checkout calendar and field styling
    └── js/
        └── checkout.js                       # Frontend Flatpickr controller & validation
```

---

## 🚀 Installation

1. Copy or upload the folder `wc-delivery-date` into your WordPress plugins directory:
   `/wp-content/plugins/wc-delivery-date/`
2. Go to **WordPress Admin > Plugins > Installed Plugins**.
3. Locate **WooCommerce Delivery Date & Time Scheduler** and click **Activate**.
4. Navigate to **WooCommerce > Delivery Date** to configure your schedule rules.

---

## ⚙️ Configuration Guide

### 1. Delivery Rules & Capacity Limits
- **Enable Delivery Scheduler**: Toggle ON to activate checkout scheduling.
- **Require Date at Checkout**: Check this box if customers must select a delivery date before placing an order.
- **Maximum Deliveries Per Day**: Enter maximum orders allowed per day (e.g., `10`). Enter `0` for unlimited.
- **Minimum Lead Time (Days)**: How many days after today's purchase before delivery can be chosen (`0` = same day, `1` = next day).
- **Maximum Advance Booking (Days)**: How many days into the future booking is open (e.g. `30`).
- **Daily Cut-off Time**: e.g., `18:00`. Orders placed after this hour will increase lead time by +1 day.
- **Allowed Delivery Weekdays**: Check only the weekdays your logistics team handles deliveries (e.g., Monday through Friday).
- **Blackout Dates & Holidays**: Enter dates when no deliveries can be scheduled (one per line, e.g. `2026-12-25`).

### 2. Delivery Time Slots
- **Enable Time Slot Selection**: Check to offer specific delivery windows.
- **Require Time Slot**: Check if picking a time slot is mandatory.
- **Available Time Slots**: Enter one time slot per line, for example:
  ```
  09:00 AM - 12:00 PM
  12:00 PM - 03:00 PM
  03:00 PM - 06:00 PM
  ```

### 3. Customer-Facing Labels & Texts
All texts shown to customers on the checkout page can be edited in English or any preferred wording.

---

## 📜 Version History & Changelog

### Version 1.1.6 (2026-10-04)
- **Store API Hook Arguments & Critical Error Fix**:
  - Resolved critical error on checkout page caused by argument count mismatch in Store API draft order hooks.
  - Hardened callback parameter defaults and null checks across Store API, transactional email, and order hooks.
  - Eliminated premature order meta persistence on unpersisted draft checkout objects.

### Version 1.1.5 (2026-10-04)
- **Transactional Emails & Order Meta Persistence Enhancement**:
  - Added delivery date & time schedule to all customer & admin transactional emails.
  - Integrated `woocommerce_email_order_meta_fields` filter and `woocommerce_email_order_meta` fallback hook.
  - Added Store API order meta synchronization (`woocommerce_store_api_checkout_update_order_meta`).
  - Added multi-key metadata fallback ensuring delivery details are retrieved across all storage mechanisms.

### Version 1.1.4 (2026-10-04)
- **Calendar Interaction & Delegated Initialization Fix**:
  - Resolved calendar opening issue on click by eliminating JavaScript syntax error.
  - Added global delegated click/focus handler ensuring calendar opens instantly under all React re-renders.
  - Extended mounting polling timer to 7.5 seconds across React hydration lifecycle.
  - Suppressed "(optional)" suffix from title label for a clean "Choose Delivery Date" header.

### Version 1.1.3 (2026-10-04)
- **Checkout Form Top Placement & Date Selection Persistence**:
  - Relocated Delivery Date & Time fields to Contact Information section at the very top of checkout.
  - Fixed selected date persistence; formatted date remains visible upon calendar closure without reverting to placeholder.
  - Added strict blur and input value protection against React re-render wiping.
  - Added classic checkout top hook (`woocommerce_before_checkout_form`).

### Version 1.1.2 (2026-10-04)
- **Checkout Blocks Validation & Position Enhancement**:
  - Fixed "Please enter a valid delivery date" error on WooCommerce Checkout Blocks.
  - Updated field title to "Choose Delivery Date" with prominent 20px typography.
  - Removed pre-selected date by default; input starts clean with placeholder until user selects a date.
  - Positioned delivery date section at the very top of the checkout form above all fields and payment options.

### Version 1.1.1 (2026-10-04)
- **Stripe & Checkout Blocks Stability Fix**:
  - Resolved browser unresponsiveness (close or wait) by eliminating recursive MutationObserver loops.
  - Fixed payment gateway mounting (Stripe and express checkout methods now load cleanly).
  - Switched checkout block reordering to native CSS Flexbox order for maximum React stability.
  - Ensured selected delivery date persistence without React state wiping.

### Version 1.1.0 (2026-10-03)
- **Checkout Blocks UI & Navigation Enhancement**:
  - Positioned "Select Delivery Date" field label clearly outside and above the selection box.
  - Fixed selected date persistence in WooCommerce Checkout Blocks (Gutenberg) to avoid placeholder overlap.
  - Fixed calendar month initialization and month navigation for future advance bookings.
  - Enhanced layout positioning immediately above Payment Options across all checkout modes.
  - Updated plugin author to P!xel Web.

### Version 1.0.0 (2026-10-03)
- **Initial Official Release**:
  - Interactive Flatpickr datepicker & time slot dropdown added to WooCommerce checkout.
  - Daily order capacity limit with real-time slot checking and automatic blocking.
  - Configurable preparation lead time (minimum days) and maximum advance booking limits.
  - Daily cut-off time support (automatically adds +1 preparation day after set hour).
  - Allowed delivery weekdays selection (Monday through Sunday).
  - Store blackout dates & holidays blocking.
  - Customizable customer-facing labels, titles, and capacity warning messages.
  - Delivery schedule displayed in Customer Thank You page and My Account > View Order.
  - Delivery schedule displayed in HTML and Plain Text transactional emails (Customer & Admin).
  - Custom "Delivery Schedule" column added to WooCommerce Admin Orders list with calendar/time icons.
  - Directly editable delivery schedule fields in Admin Order Details screen.
  - Full compatibility with WooCommerce HPOS (High-Performance Order Storage) and classic posts table.

