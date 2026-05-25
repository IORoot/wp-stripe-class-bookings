<?php
/**
 * Default admin email body (plain text).
 *
 * @package IORoot_Yoga_Bookings
 */

defined( 'ABSPATH' ) || exit;
?>
New booking received.

- Customer: {customer_name} <{customer_email}>
- Class: {class_name}
- When: {class_date} at {class_time}
- Where: {location}
- Seats: {seats}
- Total: {amount_total}
- Reference: {booking_id}
